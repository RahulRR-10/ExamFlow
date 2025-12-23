-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2025 at 11:45 PM
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
-- Database: `db_eval`
--

-- --------------------------------------------------------

--
-- Table structure for table `atmpt_list`
--

CREATE TABLE `atmpt_list` (
  `id` int(100) NOT NULL,
  `exid` int(100) NOT NULL,
  `uname` varchar(100) NOT NULL,
  `nq` int(100) NOT NULL,
  `cnq` int(100) NOT NULL,
  `ptg` int(100) NOT NULL,
  `status` int(10) NOT NULL,
  `subtime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `integrity_score` int(11) DEFAULT 100,
  `integrity_category` varchar(50) DEFAULT 'Good'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificate_nfts`
--

CREATE TABLE `certificate_nfts` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `uname` varchar(100) NOT NULL,
  `transaction_hash` varchar(255) NOT NULL,
  `token_id` varchar(100) NOT NULL,
  `contract_address` varchar(255) NOT NULL,
  `metadata_url` varchar(255) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificate_nfts`
--

INSERT INTO `certificate_nfts` (`id`, `attempt_id`, `uname`, `transaction_hash`, `token_id`, `contract_address`, `metadata_url`, `image_url`, `is_demo`, `created_at`) VALUES
(1, 16, 'student3', '0x84c301a7d82ea9b268b13c2340a4ad0a429a2bfc849b12f5e7c539aaebf61751', '9980', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmanHMsV2efRMyAgMXzKCeuW3seGhnnfSmsabcDYFkphE9', 'https://gateway.pinata.cloud/ipfs/QmP7pYbJ8hY92NT9tAsP9JzYP3QqmdhjBHA1GXZAzyRLJL', 0, '2025-04-13 21:32:34'),
(2, 17, 'student3', '0x92cf65c26a0dbfa2200e1e89a6b4df64e70b6a084619547222d7ae04a62927c3', '2698', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmanHMsV2efRMyAgMXzKCeuW3seGhnnfSmsabcDYFkphE9', 'https://gateway.pinata.cloud/ipfs/QmP7pYbJ8hY92NT9tAsP9JzYP3QqmdhjBHA1GXZAzyRLJL', 1, '2025-04-13 21:48:48'),
(3, 24, 'student3', '0x4fc857411656275c7219f08da081c8c6a09ba865e74d9a49ee5d27c84aded785', '0', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmU6HEjVBb9gg9vnbT4AVxt7aLFJkG43v5yLniJQpqJYRK', 'https://gateway.pinata.cloud/ipfs/bafybeibz3vlappauxzmunikrni6eeccr74lm7fzpxvzzeuwf6wso4qftma', 0, '2025-04-13 23:47:40'),
(4, 25, 'student3', '0xd77e03abd973ccdc87fc90acf9bda918ae787a9d8e1322c9b05d5978965b0ddc', '15', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmU6HEjVBb9gg9vnbT4AVxt7aLFJkG43v5yLniJQpqJYRK', 'https://gateway.pinata.cloud/ipfs/bafybeibz3vlappauxzmunikrni6eeccr74lm7fzpxvzzeuwf6wso4qftma', 0, '2025-04-13 23:52:05'),
(5, 26, 'student1', '0x165e57014d444d06fe959fc441423eb397932047e81b6a2cdac7f11df98bf1c6', '16', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmR4aVBnHomwD7wZdbMaQ59uk2LJqTrs62tECYjFBbFCyr', 'https://gateway.pinata.cloud/ipfs/bafybeiaykj3ijwrxgknse3jbkbphih3ynwghewnxtte3kcsxpzewt5bstq', 0, '2025-04-14 00:03:27'),
(6, 27, 'student1', '0x9d42a4ed18915c348bd646e645799e1807c88a566559d32a9f1b45b7b4ebdc6c', '17', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmUeWNin96SQKNDAzMqQadtx594uqtQXbuFPqBaA1W7BXL', 'https://gateway.pinata.cloud/ipfs/bafybeicz5fea6p24dkajmedcqkcgerstiik4rtzimxy7zgfatdgwnegs2m', 0, '2025-04-14 00:06:21'),
(7, 28, 'student2', '0x81616bb150bd1f64aaf187f0b2c17004d7f5c539252993e6a52394b3632c84da', '18', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmaUNF3C3d1zJ71agL3Njjg3J2dEfpZG4k5XE5qXHZ3nV1', 'https://gateway.pinata.cloud/ipfs/bafybeicjudwokob5rsg2v6l7khkflcgsu6en2fac72syfqvshhhln2rxiq', 0, '2025-04-14 00:17:22'),
(8, 29, 'student3', '0x13d958b56ffd6967275c9eadae04e384061a820dff1d685192ef00f1012addec', '19', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmZJzEvAnZSNsith8ZLUJJJKFUBZkm9rRt2uiqHUHVGWie', 'https://gateway.pinata.cloud/ipfs/bafybeiece3oelcl6abidss4xxxrcwqwfrjxt4pa3zcgwggrqq52irg3h6u', 0, '2025-04-14 00:39:38'),
(9, 30, 'student3', '0x30cb1abcf89d7af7389dae1f71498884d19f64605ebb509b8810f72f7074ad57', '20', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmZrnVrKHFtNFzhjGFt75jpTEdC5ug1FTh17DkknWWD5kv', 'https://gateway.pinata.cloud/ipfs/bafybeigmxcgmxh2umaivipcggzt2g2v5nzm2mgqfkm5fyta7cpxwo5cqfy', 0, '2025-04-14 00:46:27'),
(10, 31, 'student2', '0x9462f54f64cf8ad299f46282340f708cd39f63e30eb5a0646f6ce3cd511ff49c', '21', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmWPh6xXr2NVxWrEs9CKUe26qgEeYhCJsAkbnVXYZZM6KM', 'https://gateway.pinata.cloud/ipfs/bafybeif3mp2tawuv6up5tdqzdjncizeb4uhtbm74a6urj4metlqd4paada', 0, '2025-04-14 16:07:49'),
(11, 32, 'student1', '0xeed0e1de7d16ed31dd0ef92c20e7e451b6a02d52f1425e4f623b94583f0be3bc', '22', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/Qmdww3aTrJXENA9X1kvDMCTDs5kNgSf77nNnWmv26wo17L', 'https://gateway.pinata.cloud/ipfs/bafybeighzxqboapr6hb2ard7yndjorubtzflj5x4nva7amfslundk6xckm', 0, '2025-04-14 19:43:28'),
(12, 33, 'student3', '0xdf16712a7032ad5a07af8b06014a6fc98cf11f22ca7bb25c5d4542a66acdacff', '23', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmekzqQdbu3F8HNauCjgaaSdJo51LQH9QGHCMA72sTaxYC', 'https://gateway.pinata.cloud/ipfs/bafybeidy35nfxjsa2svipqxloguuw3p2w4wrv4srt4c57gwpujc6yuqjra', 0, '2025-04-14 20:18:10'),
(13, 34, 'student3', '0x0bc169e17db4cd6902bd2994f52263ec6e1a2a622319cc3b15c396212c61b2df', '24', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmNaP76bsA8FMEnD8EGXTVgBEis9wspWRa4GsP6ZBgDcng', 'https://gateway.pinata.cloud/ipfs/bafybeifrk33bb7ax3n4rdqivxernkcvnscnys7d5ksns22j4zvjr2semem', 0, '2025-04-14 20:30:32'),
(14, 35, 'student1', '0x816698d2da99c5340451ed24f249284a8e107ee86a38dd25e0ecd74c96de5a1e', '0', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmTRy9fEfQG7akiy4sw8Z5UuAS3wexvvUz3UrMz4MgK2HY', 'https://gateway.pinata.cloud/ipfs/bafybeiburqjoshf7dwccq4stitsybkhpigvnvuih6ry76lb7k5yv6o4pdq', 0, '2025-04-14 20:36:58'),
(15, 36, 'student2', '0x8719bba643018531bc178d35a5f44e4d6aefd00f161f0f0b69ccb9eebeb72340', '25', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmNeHXubLe6zssW26xbBLCMpiyG85pG36vHA46LcZQHqDW', 'https://gateway.pinata.cloud/ipfs/bafybeifi5dz2qfzm46n4csj4rzjzzon3wrmoeolnqgzrcpt66bb2jhxz6e', 0, '2025-04-14 20:42:11'),
(16, 37, 'student1', '0x867d0336472a36cea37ffa9ec6cf560cce5024b25342f143bad512ef721721f9', '26', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmPEZBUQu9wFT5VQpmkwEz8NRLB6twVXsGqRSoVCFNFpaw', 'https://gateway.pinata.cloud/ipfs/bafybeiflwzztlkqqtqamqk2dljseuxdyn76dfl4wzps2ifclzxz45l4rpi', 0, '2025-04-14 20:53:51'),
(17, 38, 'student2', '0x8d85b08583bea2232965599c6ec64cc14031eb31b9b0c4e2d295b8aa5ef4fe5d', '27', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmUp4H3n7YtptK2rowckXeFRHcYxWqA2nZ4RNtn5U9W8MB', 'https://gateway.pinata.cloud/ipfs/bafybeih3imwrjdvgmcpo5chatupm77i4n3rwynwd4lnschjjdxp2cfl73y', 0, '2025-04-14 20:59:32'),
(18, 39, 'student3', '0x922c54c0764d1850c8c1fb9e2f6c553869bfbbe35bba6b133815ef1d0bf552fd', '28', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmVpEgig8bKWKUVohdkxwVsWgkjxRH3kYKrejB3m3LD4vQ', 'https://gateway.pinata.cloud/ipfs/bafybeidjgcjpxrn7auwvr5rwr3nepz4rjyq3tlqbzv27urte5yjdrazgsy', 0, '2025-04-14 21:03:45'),
(19, 40, 'student3', '0xefedc56e82b5bce7b3815c3706c4b6752c66e86ce09a346fd56c444cfe2e6432', '29', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmfJycUfJ9KyZ6ZMPGhCZ3zhxLC2JwL3pyyozBECKLT5Tp', 'https://gateway.pinata.cloud/ipfs/bafybeidyw2lwl54qobx4mvely6njzlxjw2xaowvkmxxm4ojxwcqfc4xlby', 0, '2025-04-14 21:18:26'),
(20, 41, 'student2', '0x61b69d3f2b479122492c2244648921624c97b6b6e01dae80542e1ecd4675c9b1', '30', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmQtQui3YoRRrn8T1YrXbwDNMByDM6UTYRZ1ud1iErYaPs', 'https://gateway.pinata.cloud/ipfs/bafybeicfqyrhpxfctfgox4ejao7wor4brrhjybhp4rswowzfirb4pb4pnq', 0, '2025-04-14 21:25:36'),
(21, 42, 'student1', '0x7e2a6fb5d92622298b32a5a5b499358ed38daf1e793ab99f9541ce5bbb8faba8', '31', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/Qmej6xNZrxeMjoNZ4QmKLFome4iBHQre59e95QWqjdydYq', 'https://gateway.pinata.cloud/ipfs/bafybeifdfzjllffe7ek4a7bavjuirlck475tujnuwjgub4ktzl3pz7kqmu', 0, '2025-04-14 21:28:18'),
(22, 43, 'student1', '0xb677c1538422f2b92ed3fbf32a40d59c42fc29c666b03214c3314f28a5aae362', '0', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/Qmc8kCHLG3kviLeF4Yyb1AZKs6trchFhKDCJRVbbvKiugM', 'https://gateway.pinata.cloud/ipfs/bafybeihwbqjqbjg6cpryuijdk5krfe5e6ctsbsodxqwkn7zvltnzakelb4', 0, '2025-04-14 21:33:17'),
(23, 44, 'student2', '0x340e00e5b6c13df179f1d50c6ea1c1f80853a6b74369e5c6929f668246e92a5b', '33', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmTWWTT1615zf4QSZkLvyKx86rKsuLp6GusHLk5GUr5fuX', 'https://gateway.pinata.cloud/ipfs/bafybeidb6vrap5kv6xq2y3p74wol2jrb6uobpwfr4xs6u425iudakyc5xq', 0, '2025-04-14 21:36:17'),
(24, 45, 'student3', '0x1bd11a744392546bae8d74692ae574332e57ce24cf291d011be6b96b5338f17a', '34', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmUsta2bM1mX9QVNXhK6xo4h7ansA15yUg1xhmoDL7je45', 'https://gateway.pinata.cloud/ipfs/bafybeidwfbuerwymtlghoq2cnn6sm5i7agnics35ompz2uuluwq733iuve', 0, '2025-04-14 21:40:11'),
(25, 46, 'student1', '0x704f29b7b8263e8d682d1ca93c78785e7aa37b685cacf090ccc5cc1cfc28b992', '35', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmWghArJRe8Jqynx8fHShR9Sq3Eug2uBZWbyXP43n492h9', 'https://gateway.pinata.cloud/ipfs/bafybeiaz6qghboaefmzfe5joikeceudya2cedloyfdnj5h4k36qbtin4hm', 0, '2025-04-14 21:46:05'),
(26, 47, 'student1', '0xc27773960c54763695412fd63ad0fa874039082fd3bd9e976827ff139fc139c1', '36', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmZwTe4kzzxz7UgdyfHJqF5RwT8qsuPGrBksb9Es1KMin9', 'https://gateway.pinata.cloud/ipfs/bafybeiblb5kgqyy6rffa6tirpbgftav2lpydmixztzycuhie7fipsdioxi', 0, '2025-04-14 23:33:17'),
(27, 48, 'student1', '0xfd75c22ad1870ac31d6053d1016a827d50596b66861188b57d50354438933eee', '38', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'https://gateway.pinata.cloud/ipfs/QmY8W9KK23RBTQiQ9G9ETuMpc42kCCtgE3sVZMNJGai1fV', 'https://gateway.pinata.cloud/ipfs/bafybeifv2mqmux43rs6frlygso4w6l66dhwm7m3leixgp7bjfta5u55da4', 0, '2025-04-16 20:49:35'),
(28, 49, 'student2', '0xd5fa043fe6fbedefdc3583384c64640b3d68a806e9680032d7d831cd77f3e346', '51', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmaQw96m46eVbbwkGMPYvahHBVNYRsZa1ZjezbUgZBxwNU', 'ipfs://bafybeiglzidrrn266s4t3litkizq7ainhprtadrva2z7wt5mi63uznozbe', 0, '2025-04-18 20:11:02'),
(29, 50, 'student3', '0xc36e537ca51eca8bbcce4ae9948547aa5bf9bbb0b2c11efaa118722c0818a7d7', '52', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmTrA7ccCDbKr5rY3LFGngn5FH8ZYekhoWnX2CS46H33En', 'ipfs://bafybeif4t3c536njfvwngsk5n3m2mlzr7bho4rh4msnmabrantcz6meeuy', 0, '2025-04-18 20:15:25'),
(30, 51, 'student3', '0x70fe77d6153315377bef61f7a44c8010a74adea3f244f024d690e8dcdbc1c4f7', '56', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmauSZc6e6BKYijFJvmysgYvEmngtMJXhwHEwudVEuV6hA', 'ipfs://bafybeibatitfytjtb2ttzivb7oxohxxaimxg7kg4mkmpyqul3kvlgxq73q', 0, '2025-04-18 23:02:13'),
(31, 52, 'student3', '0xfda5a273fdd27268c5435184e3f543dd7f55bd3d1da9848044d6399a85e36cd6', '57', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmTzAztodfCScEVxmjTyENbFafLWFBRhbS1ZZLrhMyrQT9', 'ipfs://bafybeiar36jc3ar3ocuwtl52usiurc7o2xexqndc72sgqfvg7v3z2cqkvy', 0, '2025-04-18 23:38:42'),
(32, 53, 'student3', '0xe0e3a808e339a28f713c08224edae11113254500ca6fd8c93d787c9a12f4741a', '59', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmQxFdzJDsVkfmyuNv7qXP1ihDqys5318PUasLhLPZ8hAi', 'ipfs://bafybeiha4zwq2emuffonrfja47jcdmurcmema5cktxudhzv3ben7svho2y', 0, '2025-04-19 00:20:06'),
(33, 54, 'student2', '0x6dd737a1a0fa836530b2e46dd7294ab763da7d37e990763df65959c5c675c0eb', '60', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmcW5bUHAFiTrVBxvYiQCZdb3DaeEiLW4LjMwA4HsQxsMK', 'ipfs://bafybeiellnxxjwlqg3he4kua3bfytbzt2tqyfnf7t55ihhbh7ybhtgq2si', 0, '2025-04-19 00:38:10'),
(34, 55, 'student1', '0xd05370e20aa698d2fb5bb255993fbd3d970a69353071bc9d541ceb45f08668dd', '61', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmWT8vSV4vsqvnnZnLHQcFgAfVd8aUSUnXhxGjKppD5Jzq', 'ipfs://bafybeig6sml67cjanxqngjbzm4f3da4v4yw3uexnbar6fl44brlllurtpa', 0, '2025-04-19 00:45:39'),
(35, 56, 'student3', '0x674e2add9773c5645b405a224165f829e7225b1187f64f13a1ff1365ffaabdcd', '62', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmPo5ibNpH9S1WScrucmU4C5aUgrU2cvhZrf2UQNbHeG9j', 'ipfs://bafybeiegjf37344fyjea2ggnugffic5tokg3pfbly2tsyin5iimphwgvza', 0, '2025-04-19 00:53:32'),
(36, 57, 'student1', '0x76c920f64b8b4fbc0c9759b75a40b4b787c9fc471d19bd4725d349da21b051ca', '64', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmWsqZMdZdGoDpFxg81EZ7idUKgDDNbF7M1sJWA2zrRHXW', 'ipfs://bafybeigbchyeeqr7ts53rmu7s2p43x3ygdm4e2attrndqitmdxavjx5hdm', 0, '2025-04-19 03:49:33'),
(37, 58, 'student2', '0x8448a91afb97098c99428bc4de116d8787c7a7aa2111e82f5ff7f0312a1d83bc', '65', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmSfsHEi8PyyiCT6Ykddxu4uRjxuAQAj4xvVaMgFBUH2CB', 'ipfs://bafybeiftvkim27tynbqpd2khj5oo5avehlbhvblwivrpnlby4sccutjpjq', 0, '2025-04-19 03:54:56'),
(38, 59, 'student3', '0xeeaa94a5bc0d44a2f01ede629e7b4058d57f17c9a4194c2e319ff2f17d4fa226', '66', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmRCcT9V8tYKnAJMQocXLQR7LcmzgmV25MfPPEC174HxLm', 'ipfs://bafybeihwnl2aqo7z5dbsojhsackgjnf23uhsrovsldqbgh5exppuf5s5lq', 0, '2025-04-19 04:11:57'),
(39, 60, 'student1', '0x8e2c0fbc1442ab9eb41abb48210afdbc5a091ca9096ca3a26ee4cc63180d80dd', '67', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmSfPwysFqS4Nq6oSfQfo9YXknqZMX5MWCDJzFnHxPJjkL', 'ipfs://bafybeihaiee3cejuj2kvw7azezcay5434ybhkkhukc5zxnpwz24wijbq2a', 0, '2025-04-19 04:16:22'),
(40, 62, 'student2', '0x2b9b5637e96003972d6dadccb27a53b6f85369c6ff7dc9a6f0ca5e1de8bb1d07', '68', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmPEUtwKj1ZGu6gQNeUWk2Bk9UNYhhdTLQ8XoExZ3nvmc1', 'ipfs://bafybeibz4qxj6e2trbz4yba6f3vi6hpe65my5vcdbdksvykpjvqfslejvy', 0, '2025-04-19 04:20:03'),
(41, 77, 'student1', '0xded90036797ac618960b27ae5131595e16a7c1044dcc30e5f2b2f486a597caf0', '69', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://Qmd7bu4Jks7znD2LQFjNHkV9TuaoParZWPHSDKxSR9CgSC', 'ipfs://bafybeibdlvnb5fheznmgqd3np5lso2f3kwmjlmkavxmkb5vpmczx5tlgoy', 0, '2025-04-19 04:30:06'),
(42, 79, 'student3', '0x603360ff09a4905c88864f803ebf4109d7840298df7c4fbce790c51630c8173c', '72', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmSNS7q2iPxQvFmAhiiJ9S9qoqNjLXVZyCnjetD7HMLeeu', 'ipfs://bafybeiaa7dy7r7gakp6yq4pudn25jcvnpwzxbc3vdcpjd66drwhheowabm', 0, '2025-04-19 10:23:30'),
(43, 81, 'student1', '0xda44e6693257449198bb6840cbefe93271623626967b719975e3b40a7c48c56c', '74', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmZW6AkSAuLf4ae7NuMNaiF4UUvrBZhYBq4aHbD3Qj6ZZk', 'ipfs://bafybeifcvbbzgcrcfvkz3wpoxbgudi33ixbyplapih2vcfzqvsxeyavsei', 0, '2025-04-19 10:25:13'),
(44, 82, 'student2', '0x33bd115397abd690527eed4482a4d1124b42f80217b21305f896ba7990f99afc', '75', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmVureGS5nWF7SSeykzVT7RbzWyrDKTJBznCKapZXAAn1x', 'ipfs://bafybeiaglvhscyidcbkqj3a2pupn3gace6xsjlcyydzhqlo6e6uhpfftj4', 0, '2025-04-19 12:42:06'),
(45, 83, 'student2', '0xa3e902fdea439234a271482bea532b3664e78841226746b341e15bf01fb8dfaf', '77', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmVP1kitK5K9ngNBeNKkQePoxEbYP56nekvhvvAXxZe9Nr', 'ipfs://bafybeif7alpwd3rsyrhs2yl7tsuy6bxpv4vn6gswe43royl6jpxvuiessa', 0, '2025-04-19 13:37:03'),
(46, 84, 'student3', '0x85a45f03ef5ed2738f44d1e49f0d144217378c0c822721696b8b1f4c1fb02c87', '78', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmWieLdXitaFANTTLebfB5Nei6KVFv8MxoCQkX3KWTheRD', 'ipfs://bafybeignjdwqvprfyicymksmemh6tqyuer6f4md4rgovrgw3fdrybluslu', 0, '2025-04-19 15:27:38'),
(47, 85, 'student2', '0x776098f456a5efcdab7a4e8ca76d524df28af4763fdff88da0e9fcf635be5d7e', '82', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://Qmbff9TvHJ4d9MFGa6Xcfv645Axvh3LDUEocNQn4cY1bpY', 'ipfs://bafybeiagwomawtssfdpeuqx6s5kqoiw6pvlqcwlzcwop6ffaym7bxv5mki', 0, '2025-04-19 15:42:11'),
(48, 86, 'student2', '0x6a8219e82930306e16f0f5639d0bc29f3da6ee5c41a41a1b27d53ea22a16ec1a', '92', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmWjApcCAewjfytGFhdShaYNxboghHWuACVsdUk4M2np9H', 'ipfs://bafybeibbtbcr2yvr63bbl6jj6n7d2mtsj6asfqpr2gbo23xprwlr2ntpf4', 0, '2025-04-19 19:32:24'),
(49, 87, 'student2', '0x802a9b9aa287ff622c260a3cfeb3771e6456a71e7c095b505191e9d044ddb9f0', '99', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmfRnGNw9ufS4VNybQsk8QH6fSUU9Wc3JhFbE1XTkqvuf3', 'ipfs://bafybeig5n6iyrizvkxnoexqdpx3i7xhxy7nxctuqj6qcdzeggks5agl46q', 0, '2025-04-19 22:03:03'),
(50, 88, 'student3', '0x19d6979cf0ab03f214478ad6f66df8b41b7bac6757325808ec87e162c786e490', '100', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmZZb7grVsmbxNexyTN2ThicDCdZbvcahQGuCuZnhNGShb', 'ipfs://bafybeibozdjfxxnclf4jtc76aoaehkaieoxeammtm7pooxgejevpmlt5mi', 0, '2025-04-19 22:06:13'),
(51, 92, 'student1', '0x4123164067e9d8d8e133b68b02e48864a7642d155c3fb4fa305b1c2c3dcf05bf', '101', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmaUS4EHYPBGPGvsd9QBXkBgmfp81rCU2LP6Btg9qQerF3', 'ipfs://bafybeia7qq45wil3gw5fmh2bf74nqamb56gyxktskftehyz2paujwnnaui', 0, '2025-04-19 22:14:05'),
(52, 93, 'student1', '0xbcbdcd50ed6fcf04debe9135460f3be7038fa50464f77b80f3f5bb4cb7ad549a', '102', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmZoZsFcqect1CumMYV15udcFZQDRk1xUUce8EwQuC84C3', 'ipfs://bafybeibbvabavrfbwzxaz3p73tvfah6rgqauofvsen3jgqqsm6lke2ytzi', 0, '2025-04-19 22:56:26'),
(53, 94, 'student1', '0x0c046d36d2b19da15ea8cd91b0c6c36a70400b6d1ff38e412faf74eda1ab21ee', '106', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmNmqPy8u3ZiHTDEaPE7hqVML1t34MUFcSvDuzKnyERr1k', 'ipfs://bafybeiaect773d66jq574vh2fc3nspohehe5sp2n23othjh6cilted6ofe', 0, '2025-04-20 00:25:45'),
(54, 105, 'student2', '0x618b0e16b1321b1a25010fdc4c5e8d14d60846c46fef9ad6f9e3d291c7bae002', '108', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmRCdCWVLTYDetWc51cZRezVqoAYGic9TS1NByQsjhAK5n', 'ipfs://bafybeifejvpmessjxmygsgbcbs5kolmbvy362j3x3c3thznzlrzrlmx2iy', 0, '2025-04-20 01:56:14'),
(55, 106, 'student2', '0x4a76e5edd6f01c41a0cfbd9e4d99d894a65af8eae7fb7fdb3339b34af9dfb69a', '109', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmPK8nY2kpcXknWEKaga1FLUQbz4anEBj9ybYL5kHX7wGp', 'ipfs://bafybeih66bqjr2w7627kzl6rwoup67dmcrjexhapmch7wjfqwwvods2f7q', 0, '2025-04-20 02:01:02'),
(56, 107, 'student1', '0x47f3bc3b7478fc316859bb0a8b0a4f03c05047953eac3b2ad1f8d7d29827037a', '111', '0x8cFe8F5395c87522Ce96915c2B492960bd63633E', 'ipfs://QmddXheWMzsGxMsvuFqJnZqkHDeePVrHDPY9KczrKbJRv6', 'ipfs://bafybeieuhmv7vzqtoun6wfsdjeanyafxxdlui6jdbufzm5r7dk25uktjm4', 0, '2025-04-20 02:14:03'),
(57, 108, 'student2', '0xbd99b7a8b95cc73c44e1700f1e7ae414d4fbc3a1b19c17ec3b6b29a1af4e6fcf', '5', '0xfE9c584F6360966B949a8804414B07C546a6F69F', 'ipfs://QmaUrGbVnUkBmWDc9awd7NyvUie9MopUtQYazxcMNiTf3H', 'ipfs://bafybeictwq6pmi6ml2arc2gmfls6ytwwjtv3jj3tpbdcjcvemtyqcgx6aa', 0, '2025-04-20 02:19:25'),
(58, 109, 'student3', '0x12ffd6bf1a328ae6acbe5f51c4ac2225f14e124a4bdf9079302ea926d10b32e7', '6', '0xfE9c584F6360966B949a8804414B07C546a6F69F', 'ipfs://QmXk2VJtRaniYFJ9TK4w91NPxrcuQop6rdAWPbLvXtG1nL', 'ipfs://bafybeibvds3mbnsmvdetkff4f5xfg22ugqqizk6ov5rpzakdmxgzyt3g6u', 0, '2025-04-20 02:23:03'),
(59, 110, 'student3', '0x2338e79265a0762454004296439769f0cbd5f9296c5c35f33b6459d28d271a5e', '11', '0xfE9c584F6360966B949a8804414B07C546a6F69F', 'ipfs://QmNXoQqbEd4YWGxoxDozyxjq1LCuYEDAvG6bXeRBw4KzeT', 'ipfs://bafkreienddcyvjztridjqk2osknwhubzfyedprargxm5rq3sv3u5rvsycy', 0, '2025-04-20 02:48:38');

-- --------------------------------------------------------

--
-- Table structure for table `cheat_violations`
--

CREATE TABLE `cheat_violations` (
  `id` int(11) NOT NULL,
  `student_username` varchar(100) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `violation_type` varchar(50) NOT NULL,
  `occurrence` int(11) NOT NULL,
  `penalty` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cheat_violations`
--

INSERT INTO `cheat_violations` (`id`, `student_username`, `exam_id`, `violation_type`, `occurrence`, `penalty`, `timestamp`) VALUES
(1, 'student3', 29, 'tab_switch', 1, 3, '2025-04-14 14:46:43'),
(2, 'student3', 29, 'tab_switch', 2, 5, '2025-04-14 14:47:05'),
(3, 'student3', 29, 'tab_switch', 3, 8, '2025-04-14 14:47:12'),
(4, 'student3', 29, 'tab_switch', 4, 8, '2025-04-14 14:47:36'),
(5, 'student3', 29, 'tab_switch', 5, 15, '2025-04-14 14:47:44'),
(6, 'student3', 29, 'tab_switch', 6, 15, '2025-04-14 14:47:48'),
(7, 'student3', 29, 'tab_switch', 7, 15, '2025-04-14 14:47:51'),
(8, 'student3', 29, 'tab_switch', 8, 15, '2025-04-14 14:47:56'),
(9, 'student3', 29, 'tab_switch', 9, 15, '2025-04-14 14:48:00'),
(10, 'student3', 29, 'tab_switch', 10, 15, '2025-04-14 14:48:03'),
(11, 'student3', 30, 'window_blur', 1, 2, '2025-04-14 14:59:48'),
(12, 'student3', 30, 'window_blur', 2, 4, '2025-04-14 14:59:57'),
(13, 'student3', 30, 'window_blur', 3, 6, '2025-04-14 15:00:04'),
(14, 'student3', 30, 'window_blur', 4, 8, '2025-04-14 15:00:07'),
(15, 'student3', 30, 'tab_switch', 1, 3, '2025-04-14 15:00:08'),
(16, 'student3', 30, 'window_blur', 5, 8, '2025-04-14 15:00:11'),
(17, 'student3', 30, 'tab_switch', 2, 5, '2025-04-14 15:00:11'),
(18, 'student3', 30, 'window_blur', 6, 8, '2025-04-14 15:00:18'),
(19, 'student3', 30, 'tab_switch', 3, 8, '2025-04-14 15:00:18'),
(20, 'student3', 30, 'tab_switch', 4, 8, '2025-04-14 15:00:22'),
(21, 'student3', 30, 'window_blur', 7, 8, '2025-04-14 15:00:22'),
(22, 'student3', 30, 'tab_switch', 5, 15, '2025-04-14 15:00:25'),
(23, 'student1', 30, 'window_blur', 1, 2, '2025-04-14 15:06:30'),
(24, 'student1', 30, 'tab_switch', 1, 3, '2025-04-14 15:06:31'),
(25, 'student1', 30, 'window_blur', 2, 4, '2025-04-14 15:06:38'),
(26, 'student1', 30, 'window_blur', 3, 6, '2025-04-14 15:06:45'),
(27, 'student1', 30, 'tab_switch', 2, 5, '2025-04-14 15:06:45'),
(28, 'student1', 30, 'window_blur', 4, 8, '2025-04-14 15:06:48'),
(29, 'student1', 30, 'tab_switch', 3, 8, '2025-04-14 15:06:51'),
(30, 'student2', 30, 'window_blur', 1, 2, '2025-04-14 15:11:59'),
(31, 'student2', 30, 'tab_switch', 1, 3, '2025-04-14 15:12:00'),
(32, 'student2', 30, 'tab_switch', 2, 5, '2025-04-14 15:12:05'),
(33, 'student1', 31, 'window_blur', 1, 2, '2025-04-14 15:23:17'),
(34, 'student1', 31, 'window_blur', 2, 4, '2025-04-14 15:23:18'),
(35, 'student1', 31, 'combined', 1, 10, '2025-04-14 15:23:18'),
(36, 'student1', 31, 'tab_switch', 1, 3, '2025-04-14 15:23:18'),
(37, 'student1', 31, 'window_blur', 3, 6, '2025-04-14 15:23:21'),
(38, 'student1', 31, 'window_blur', 4, 8, '2025-04-14 15:23:22'),
(39, 'student1', 31, 'combined', 2, 15, '2025-04-14 15:23:22'),
(40, 'student1', 31, 'tab_switch', 2, 5, '2025-04-14 15:23:22'),
(41, 'student1', 31, 'window_blur', 5, 8, '2025-04-14 15:23:26'),
(42, 'student1', 31, 'combined', 3, 20, '2025-04-14 15:23:26'),
(43, 'student1', 31, 'tab_switch', 3, 8, '2025-04-14 15:23:26'),
(44, 'student1', 31, 'combined', 4, 20, '2025-04-14 15:23:27'),
(45, 'student1', 31, 'window_blur', 6, 8, '2025-04-14 15:23:27'),
(46, 'student1', 31, 'combined', 5, 20, '2025-04-14 15:23:27'),
(47, 'student1', 31, 'tab_switch', 4, 15, '2025-04-14 15:23:27'),
(48, 'student1', 31, 'window_blur', 7, 8, '2025-04-14 15:23:29'),
(49, 'student1', 31, 'window_blur', 8, 8, '2025-04-14 15:23:32'),
(50, 'student1', 31, 'window_blur', 9, 8, '2025-04-14 15:23:33'),
(51, 'student1', 31, 'window_blur', 10, 8, '2025-04-14 15:23:34'),
(52, 'student1', 31, 'combined', 6, 20, '2025-04-14 15:23:34'),
(53, 'student1', 31, 'tab_switch', 5, 15, '2025-04-14 15:23:34'),
(54, 'student1', 31, 'window_blur', 11, 8, '2025-04-14 15:23:35'),
(55, 'student1', 31, 'combined', 7, 20, '2025-04-14 15:23:35'),
(56, 'student1', 31, 'combined', 8, 20, '2025-04-14 15:23:35'),
(57, 'student1', 31, 'tab_switch', 6, 15, '2025-04-14 15:23:35'),
(58, 'student1', 31, 'combined', 9, 20, '2025-04-14 15:23:36'),
(59, 'student1', 31, 'window_blur', 12, 8, '2025-04-14 15:23:36'),
(60, 'student1', 31, 'combined', 10, 20, '2025-04-14 15:23:36'),
(61, 'student1', 31, 'tab_switch', 7, 15, '2025-04-14 15:23:36'),
(62, 'student1', 31, 'tab_switch', 8, 15, '2025-04-14 15:23:43'),
(63, 'student2', 31, 'window_blur', 1, 2, '2025-04-14 15:29:14'),
(64, 'student2', 31, 'combined', 1, 10, '2025-04-14 15:29:14'),
(65, 'student2', 31, 'tab_switch', 1, 3, '2025-04-14 15:29:14'),
(66, 'student2', 31, 'window_blur', 2, 4, '2025-04-14 15:29:18'),
(67, 'student2', 31, 'window_blur', 3, 6, '2025-04-14 15:29:22'),
(68, 'student2', 31, 'tab_switch', 2, 5, '2025-04-14 15:29:25'),
(69, 'student3', 31, 'window_blur', 1, 2, '2025-04-14 15:33:35'),
(70, 'student3', 31, 'combined', 1, 10, '2025-04-14 15:33:36'),
(71, 'student3', 31, 'tab_switch', 1, 3, '2025-04-14 15:33:36'),
(72, 'student3', 31, 'tab_switch', 2, 5, '2025-04-14 15:33:38'),
(73, 'student3', 32, 'window_blur', 1, 2, '2025-04-14 15:48:00'),
(74, 'student3', 32, 'combined', 1, 10, '2025-04-14 15:48:00'),
(75, 'student3', 32, 'tab_switch', 1, 3, '2025-04-14 15:48:00'),
(76, 'student3', 32, 'window_blur', 2, 4, '2025-04-14 15:48:06'),
(77, 'student3', 32, 'window_blur', 3, 6, '2025-04-14 15:48:10'),
(78, 'student3', 32, 'combined', 2, 15, '2025-04-14 15:48:10'),
(79, 'student3', 32, 'tab_switch', 2, 5, '2025-04-14 15:48:10'),
(80, 'student3', 32, 'combined', 3, 20, '2025-04-14 15:48:10'),
(81, 'student3', 32, 'window_blur', 4, 8, '2025-04-14 15:48:10'),
(82, 'student3', 32, 'window_blur', 5, 8, '2025-04-14 15:48:12'),
(83, 'student3', 32, 'window_blur', 6, 8, '2025-04-14 15:48:12'),
(84, 'student3', 32, 'tab_switch', 3, 8, '2025-04-14 15:48:19'),
(85, 'student2', 32, 'window_blur', 1, 2, '2025-04-14 15:54:41'),
(86, 'student2', 32, 'combined', 1, 10, '2025-04-14 15:54:41'),
(87, 'student2', 32, 'tab_switch', 1, 3, '2025-04-14 15:54:41'),
(88, 'student2', 32, 'window_blur', 2, 4, '2025-04-14 15:54:53'),
(89, 'student2', 32, 'combined', 2, 15, '2025-04-14 15:54:53'),
(90, 'student2', 32, 'tab_switch', 2, 5, '2025-04-14 15:54:53'),
(91, 'student2', 32, 'window_blur', 3, 6, '2025-04-14 15:55:01'),
(92, 'student2', 32, 'combined', 3, 20, '2025-04-14 15:55:01'),
(93, 'student2', 32, 'tab_switch', 3, 8, '2025-04-14 15:55:02'),
(94, 'student2', 32, 'window_blur', 4, 8, '2025-04-14 15:55:07'),
(95, 'student2', 32, 'combined', 4, 20, '2025-04-14 15:55:08'),
(96, 'student2', 32, 'tab_switch', 4, 15, '2025-04-14 15:55:08'),
(97, 'student2', 32, 'window_blur', 5, 8, '2025-04-14 15:55:13'),
(98, 'student2', 32, 'combined', 5, 20, '2025-04-14 15:55:13'),
(99, 'student2', 32, 'tab_switch', 5, 15, '2025-04-14 15:55:13'),
(100, 'student2', 32, 'window_blur', 6, 8, '2025-04-14 15:55:21'),
(101, 'student2', 32, 'combined', 6, 20, '2025-04-14 15:55:21'),
(102, 'student2', 32, 'tab_switch', 6, 15, '2025-04-14 15:55:21'),
(103, 'student2', 32, 'window_blur', 7, 8, '2025-04-14 15:55:24'),
(104, 'student2', 32, 'combined', 7, 20, '2025-04-14 15:55:24'),
(105, 'student2', 32, 'tab_switch', 7, 15, '2025-04-14 15:55:24'),
(106, 'student2', 32, 'tab_switch', 8, 15, '2025-04-14 15:55:29'),
(107, 'student1', 32, 'window_blur', 1, 2, '2025-04-14 15:57:25'),
(108, 'student1', 32, 'tab_switch', 1, 3, '2025-04-14 15:57:25'),
(109, 'student1', 32, 'combined', 1, 10, '2025-04-14 15:57:25'),
(110, 'student1', 32, 'window_blur', 2, 4, '2025-04-14 15:57:35'),
(111, 'student1', 32, 'combined', 2, 15, '2025-04-14 15:57:35'),
(112, 'student1', 32, 'tab_switch', 2, 5, '2025-04-14 15:57:35'),
(113, 'student1', 32, 'window_blur', 3, 6, '2025-04-14 15:57:43'),
(114, 'student1', 32, 'combined', 3, 20, '2025-04-14 15:57:43'),
(115, 'student1', 32, 'tab_switch', 3, 8, '2025-04-14 15:57:43'),
(116, 'student1', 32, 'window_blur', 4, 8, '2025-04-14 15:57:46'),
(117, 'student1', 32, 'combined', 4, 20, '2025-04-14 15:57:46'),
(118, 'student1', 32, 'tab_switch', 4, 15, '2025-04-14 15:57:46'),
(119, 'student1', 32, 'window_blur', 5, 8, '2025-04-14 15:57:57'),
(120, 'student1', 32, 'combined', 5, 20, '2025-04-14 15:57:57'),
(121, 'student1', 32, 'tab_switch', 5, 15, '2025-04-14 15:57:57'),
(122, 'student1', 32, 'window_blur', 6, 8, '2025-04-14 15:58:05'),
(123, 'student1', 32, 'combined', 6, 20, '2025-04-14 15:58:05'),
(124, 'student1', 32, 'tab_switch', 6, 15, '2025-04-14 15:58:05'),
(125, 'student1', 32, 'combined', 7, 20, '2025-04-14 15:58:06'),
(126, 'student1', 32, 'window_blur', 7, 8, '2025-04-14 15:58:06'),
(127, 'student1', 32, 'tab_switch', 7, 15, '2025-04-14 15:58:06'),
(128, 'student1', 32, 'combined', 8, 20, '2025-04-14 15:58:06'),
(129, 'student1', 32, 'combined', 9, 20, '2025-04-14 15:58:06'),
(130, 'student1', 32, 'window_blur', 8, 8, '2025-04-14 15:58:06'),
(131, 'student1', 32, 'combined', 10, 20, '2025-04-14 15:58:06'),
(132, 'student1', 32, 'tab_switch', 8, 15, '2025-04-14 15:58:06'),
(133, 'student1', 32, 'window_blur', 9, 8, '2025-04-14 15:58:07'),
(134, 'student1', 32, 'combined', 11, 20, '2025-04-14 15:58:07'),
(135, 'student1', 32, 'combined', 12, 20, '2025-04-14 15:58:07'),
(136, 'student1', 32, 'tab_switch', 9, 15, '2025-04-14 15:58:07'),
(137, 'student1', 32, 'combined', 13, 20, '2025-04-14 15:58:07'),
(138, 'student1', 32, 'window_blur', 10, 8, '2025-04-14 15:58:07'),
(139, 'student1', 32, 'tab_switch', 10, 15, '2025-04-14 15:58:07'),
(140, 'student1', 32, 'combined', 14, 20, '2025-04-14 15:58:07'),
(141, 'student1', 33, 'window_blur', 1, 2, '2025-04-14 16:02:40'),
(142, 'student1', 33, 'window_blur', 2, 4, '2025-04-14 16:02:42'),
(143, 'student1', 33, 'window_blur', 3, 6, '2025-04-14 16:02:44'),
(144, 'student1', 33, 'window_blur', 4, 8, '2025-04-14 16:02:49'),
(145, 'student1', 33, 'combined', 1, 10, '2025-04-14 16:02:49'),
(146, 'student1', 33, 'tab_switch', 1, 3, '2025-04-14 16:02:49'),
(147, 'student1', 33, 'window_blur', 5, 8, '2025-04-14 16:02:55'),
(148, 'student1', 33, 'combined', 2, 15, '2025-04-14 16:02:55'),
(149, 'student1', 33, 'tab_switch', 2, 5, '2025-04-14 16:02:55'),
(150, 'student2', 33, 'window_blur', 1, 2, '2025-04-14 16:05:29'),
(151, 'student2', 33, 'combined', 1, 10, '2025-04-14 16:05:29'),
(152, 'student2', 33, 'tab_switch', 1, 3, '2025-04-14 16:05:29'),
(153, 'student2', 33, 'combined', 2, 15, '2025-04-14 16:05:36'),
(154, 'student2', 33, 'window_blur', 2, 4, '2025-04-14 16:05:36'),
(155, 'student2', 33, 'tab_switch', 2, 5, '2025-04-14 16:05:36'),
(156, 'student2', 33, 'window_blur', 3, 6, '2025-04-14 16:05:38'),
(157, 'student2', 33, 'combined', 3, 20, '2025-04-14 16:05:38'),
(158, 'student2', 33, 'tab_switch', 3, 8, '2025-04-14 16:05:38'),
(159, 'student2', 33, 'window_blur', 4, 8, '2025-04-14 16:05:41'),
(160, 'student2', 33, 'combined', 4, 20, '2025-04-14 16:05:41'),
(161, 'student2', 33, 'tab_switch', 4, 15, '2025-04-14 16:05:41'),
(162, 'student2', 33, 'combined', 5, 20, '2025-04-14 16:05:42'),
(163, 'student2', 33, 'window_blur', 5, 8, '2025-04-14 16:05:42'),
(164, 'student2', 33, 'combined', 6, 20, '2025-04-14 16:05:42'),
(165, 'student2', 33, 'tab_switch', 5, 15, '2025-04-14 16:05:42'),
(166, 'student3', 33, 'window_blur', 1, 2, '2025-04-14 16:09:42'),
(167, 'student3', 33, 'window_blur', 2, 4, '2025-04-14 16:09:43'),
(168, 'student3', 33, 'window_blur', 3, 6, '2025-04-14 16:09:44'),
(169, 'student3', 33, 'window_blur', 4, 8, '2025-04-14 16:09:45'),
(170, 'student3', 33, 'window_blur', 5, 8, '2025-04-14 16:09:46'),
(171, 'student3', 33, 'window_blur', 6, 8, '2025-04-14 16:09:47'),
(172, 'student3', 33, 'window_blur', 7, 8, '2025-04-14 16:09:48'),
(173, 'student3', 33, 'window_blur', 8, 8, '2025-04-14 16:09:49'),
(174, 'student3', 33, 'window_blur', 9, 8, '2025-04-14 16:09:50'),
(175, 'student3', 33, 'window_blur', 10, 8, '2025-04-14 16:09:51'),
(176, 'student3', 33, 'window_blur', 11, 8, '2025-04-14 16:09:53'),
(177, 'student3', 33, 'window_blur', 12, 8, '2025-04-14 16:09:54'),
(178, 'student3', 33, 'window_blur', 13, 8, '2025-04-14 16:10:04'),
(179, 'student1', 34, 'window_blur', 1, 2, '2025-04-14 16:15:48'),
(180, 'student1', 34, 'window_blur', 2, 4, '2025-04-14 16:15:52'),
(181, 'student1', 34, 'window_blur', 3, 6, '2025-04-14 16:15:54'),
(182, 'student1', 34, 'combined', 1, 10, '2025-04-14 16:15:54'),
(183, 'student1', 34, 'tab_switch', 1, 3, '2025-04-14 16:15:54'),
(184, 'student1', 35, 'window_blur', 1, 2, '2025-04-14 18:01:52'),
(185, 'student1', 35, 'window_blur', 2, 4, '2025-04-14 18:01:58'),
(186, 'student1', 35, 'window_blur', 3, 6, '2025-04-14 18:02:14'),
(187, 'student1', 35, 'combined', 1, 10, '2025-04-14 18:02:14'),
(188, 'student1', 35, 'tab_switch', 1, 3, '2025-04-14 18:02:14'),
(189, 'student1', 35, 'window_blur', 4, 8, '2025-04-14 18:02:59'),
(190, 'student1', 35, 'window_blur', 5, 8, '2025-04-14 18:03:01'),
(191, 'student1', 35, 'window_blur', 6, 8, '2025-04-14 18:03:02'),
(192, 'student1', 35, 'window_blur', 7, 8, '2025-04-14 18:03:03'),
(193, 'student1', 35, 'tab_switch', 2, 5, '2025-04-14 18:03:09'),
(194, 'student1', 36, 'window_blur', 1, 2, '2025-04-16 15:18:29'),
(195, 'student1', 36, 'combined', 1, 10, '2025-04-16 15:18:29'),
(196, 'student1', 36, 'tab_switch', 1, 3, '2025-04-16 15:18:29'),
(197, 'student1', 36, 'window_blur', 2, 4, '2025-04-16 15:18:37'),
(198, 'student1', 36, 'window_blur', 3, 6, '2025-04-16 15:18:42'),
(199, 'student1', 36, 'window_blur', 4, 8, '2025-04-16 15:18:43'),
(200, 'student1', 36, 'combined', 2, 15, '2025-04-16 15:18:43'),
(201, 'student1', 36, 'tab_switch', 2, 5, '2025-04-16 15:18:43'),
(202, 'student1', 36, 'window_blur', 5, 8, '2025-04-16 15:19:18'),
(203, 'student1', 36, 'combined', 3, 20, '2025-04-16 15:19:18'),
(204, 'student1', 36, 'tab_switch', 3, 8, '2025-04-16 15:19:18'),
(205, 'student1', 36, 'tab_switch', 4, 15, '2025-04-16 15:19:27'),
(206, 'student2', 34, 'tab_switch', 1, 3, '2025-04-18 14:40:54'),
(207, 'student3', 36, 'window_blur', 1, 2, '2025-04-18 14:45:11'),
(208, 'student3', 36, 'window_blur', 2, 4, '2025-04-18 14:45:12'),
(209, 'student3', 36, 'window_blur', 3, 6, '2025-04-18 14:45:13'),
(210, 'student3', 36, 'combined', 1, 10, '2025-04-18 14:45:14'),
(211, 'student3', 36, 'tab_switch', 1, 3, '2025-04-18 14:45:14'),
(212, 'student3', 36, 'tab_switch', 2, 5, '2025-04-18 14:45:16'),
(213, 'student3', 37, 'window_blur', 1, 2, '2025-04-18 17:32:02'),
(214, 'student3', 37, 'tab_switch', 1, 3, '2025-04-18 17:32:06'),
(215, 'student3', 47, 'window_blur', 1, 2, '2025-04-18 18:08:29'),
(216, 'student3', 47, 'tab_switch', 1, 3, '2025-04-18 18:08:34'),
(217, 'student3', 49, 'window_blur', 1, 2, '2025-04-18 18:49:56'),
(218, 'student3', 49, 'tab_switch', 1, 3, '2025-04-18 18:49:59'),
(219, 'student1', 50, 'window_blur', 1, 2, '2025-04-18 19:15:24'),
(220, 'student1', 50, 'window_blur', 2, 4, '2025-04-18 19:15:26'),
(221, 'student1', 50, 'window_blur', 3, 6, '2025-04-18 19:15:27'),
(222, 'student1', 60, 'window_blur', 1, 2, '2025-04-18 22:15:02'),
(223, 'student1', 60, 'window_blur', 2, 4, '2025-04-18 22:15:04'),
(224, 'student1', 60, 'window_blur', 3, 6, '2025-04-18 22:15:06'),
(225, 'student1', 60, 'window_blur', 4, 8, '2025-04-18 22:15:10'),
(226, 'student1', 60, 'window_blur', 5, 8, '2025-04-18 22:15:13'),
(227, 'student1', 61, 'window_blur', 1, 2, '2025-04-18 22:45:38'),
(228, 'student1', 61, 'combined', 1, 10, '2025-04-18 22:45:39'),
(229, 'student1', 61, 'tab_switch', 1, 3, '2025-04-18 22:45:39'),
(230, 'student1', 61, 'window_blur', 2, 4, '2025-04-18 22:45:41'),
(231, 'student1', 61, 'combined', 2, 15, '2025-04-18 22:45:42'),
(232, 'student1', 61, 'tab_switch', 2, 5, '2025-04-18 22:45:42'),
(233, 'student1', 61, 'window_blur', 3, 6, '2025-04-18 22:45:45'),
(234, 'student1', 61, 'combined', 3, 20, '2025-04-18 22:45:45'),
(235, 'student1', 61, 'tab_switch', 3, 8, '2025-04-18 22:45:45'),
(236, 'student1', 61, 'window_blur', 4, 8, '2025-04-18 22:46:01'),
(237, 'student2', 61, 'window_blur', 1, 2, '2025-04-18 22:48:15'),
(238, 'student2', 61, 'combined', 1, 10, '2025-04-18 22:48:16'),
(239, 'student2', 61, 'tab_switch', 1, 3, '2025-04-18 22:48:16'),
(240, 'student2', 61, 'window_blur', 2, 4, '2025-04-18 22:48:45'),
(241, 'student2', 61, 'combined', 2, 15, '2025-04-18 22:48:45'),
(242, 'student2', 61, 'tab_switch', 2, 5, '2025-04-18 22:48:45'),
(243, 'student2', 61, 'window_blur', 3, 6, '2025-04-18 22:49:01'),
(244, 'student2', 61, 'combined', 3, 20, '2025-04-18 22:49:02'),
(245, 'student2', 61, 'tab_switch', 3, 8, '2025-04-18 22:49:02'),
(246, 'student2', 61, 'window_blur', 4, 8, '2025-04-18 22:49:14'),
(247, 'student2', 61, 'window_blur', 5, 8, '2025-04-18 22:49:17'),
(248, 'student2', 61, 'combined', 4, 20, '2025-04-18 22:49:17'),
(249, 'student2', 61, 'tab_switch', 4, 15, '2025-04-18 22:49:17'),
(250, 'student2', 61, 'window_blur', 6, 8, '2025-04-18 22:49:53'),
(251, 'student2', 61, 'window_blur', 7, 8, '2025-04-18 22:49:55'),
(252, 'student2', 61, 'window_blur', 8, 8, '2025-04-18 22:49:56'),
(253, 'student2', 61, 'tab_switch', 5, 15, '2025-04-18 22:49:56'),
(254, 'student2', 61, 'combined', 5, 20, '2025-04-18 22:49:56'),
(255, 'student3', 61, 'window_blur', 1, 2, '2025-04-18 22:51:00'),
(256, 'student3', 61, 'combined', 1, 10, '2025-04-18 22:51:01'),
(257, 'student3', 61, 'tab_switch', 1, 3, '2025-04-18 22:51:01'),
(258, 'student3', 61, 'window_blur', 2, 4, '2025-04-18 22:51:57'),
(259, 'student3', 61, 'combined', 2, 15, '2025-04-18 22:51:58'),
(260, 'student3', 61, 'tab_switch', 2, 5, '2025-04-18 22:51:58'),
(261, 'student3', 61, 'tab_switch', 3, 8, '2025-04-18 22:52:01'),
(262, 'student3', 61, 'window_blur', 3, 6, '2025-04-18 22:52:48'),
(263, 'student3', 61, 'window_blur', 4, 8, '2025-04-18 22:52:49'),
(264, 'student3', 61, 'window_blur', 5, 8, '2025-04-18 22:52:51'),
(265, 'student3', 61, 'window_blur', 6, 8, '2025-04-18 22:52:51'),
(266, 'student3', 61, 'window_blur', 7, 8, '2025-04-18 22:52:52'),
(267, 'student3', 61, 'window_blur', 8, 8, '2025-04-18 22:52:53'),
(268, 'student3', 61, 'window_blur', 9, 8, '2025-04-18 22:52:54'),
(269, 'student3', 61, 'window_blur', 10, 8, '2025-04-18 22:52:55'),
(270, 'student3', 61, 'window_blur', 11, 8, '2025-04-18 22:53:02'),
(271, 'student3', 61, 'window_blur', 12, 8, '2025-04-18 22:53:03'),
(272, 'student3', 61, 'window_blur', 13, 8, '2025-04-18 22:53:04'),
(273, 'student3', 61, 'window_blur', 14, 8, '2025-04-18 22:53:05'),
(274, 'student3', 61, 'window_blur', 15, 8, '2025-04-18 22:53:05'),
(275, 'student3', 61, 'window_blur', 16, 8, '2025-04-18 22:53:07'),
(276, 'student3', 61, 'window_blur', 17, 8, '2025-04-18 22:53:08'),
(277, 'student3', 61, 'window_blur', 18, 8, '2025-04-18 22:53:15'),
(278, 'student3', 61, 'window_blur', 19, 8, '2025-04-18 22:53:16'),
(279, 'student3', 61, 'window_blur', 20, 8, '2025-04-18 22:53:21'),
(280, 'student3', 61, 'window_blur', 21, 8, '2025-04-18 22:53:22'),
(281, 'student3', 61, 'window_blur', 22, 8, '2025-04-18 22:53:23'),
(282, 'student3', 61, 'window_blur', 23, 8, '2025-04-18 22:53:24'),
(283, 'student3', 61, 'window_blur', 24, 8, '2025-04-18 22:53:25'),
(284, 'student3', 61, 'window_blur', 25, 8, '2025-04-18 22:53:26'),
(285, 'student3', 61, 'window_blur', 26, 8, '2025-04-18 22:53:35'),
(286, 'student3', 61, 'window_blur', 27, 8, '2025-04-18 22:53:36'),
(287, 'student3', 61, 'window_blur', 28, 8, '2025-04-18 22:53:37'),
(288, 'student3', 61, 'window_blur', 29, 8, '2025-04-18 22:53:38'),
(289, 'student3', 61, 'window_blur', 30, 8, '2025-04-18 22:53:38'),
(290, 'student3', 61, 'window_blur', 31, 8, '2025-04-18 22:53:39'),
(291, 'student3', 61, 'window_blur', 32, 8, '2025-04-18 22:53:40'),
(292, 'student2', 62, 'tab_switch', 1, 3, '2025-04-18 23:02:03'),
(293, 'student2', 62, 'combined', 1, 10, '2025-04-18 23:02:03'),
(294, 'student2', 62, 'window_blur', 1, 2, '2025-04-18 23:02:03'),
(295, 'student3', 62, 'window_blur', 1, 2, '2025-04-19 04:53:13'),
(296, 'student3', 62, 'window_blur', 2, 4, '2025-04-19 04:53:14'),
(297, 'student3', 62, 'tab_switch', 1, 3, '2025-04-19 04:53:23'),
(298, 'student3', 64, 'window_blur', 1, 2, '2025-04-19 09:57:28'),
(299, 'student3', 64, 'combined', 1, 10, '2025-04-19 09:57:30'),
(300, 'student3', 64, 'tab_switch', 1, 3, '2025-04-19 09:57:30'),
(301, 'student2', 65, 'tab_switch', 1, 3, '2025-04-19 10:12:04'),
(302, 'student2', 66, 'tab_switch', 1, 3, '2025-04-19 14:02:16'),
(303, 'student2', 68, 'window_blur', 1, 2, '2025-04-19 16:32:42'),
(304, 'student2', 68, 'window_blur', 2, 4, '2025-04-19 16:32:44'),
(305, 'student2', 68, 'combined', 1, 10, '2025-04-19 16:32:44'),
(306, 'student2', 68, 'tab_switch', 1, 3, '2025-04-19 16:32:44'),
(307, 'student2', 68, 'window_blur', 3, 6, '2025-04-19 16:32:50'),
(308, 'student2', 68, 'combined', 2, 15, '2025-04-19 16:32:50'),
(309, 'student2', 68, 'tab_switch', 2, 5, '2025-04-19 16:32:50'),
(310, 'student2', 68, 'tab_switch', 3, 8, '2025-04-19 16:32:57'),
(311, 'student3', 68, 'tab_switch', 1, 3, '2025-04-19 16:36:06'),
(312, 'student3', 70, 'tab_switch', 1, 3, '2025-04-19 16:39:03'),
(313, 'student3', 69, 'tab_switch', 1, 3, '2025-04-19 16:42:58'),
(314, 'student1', 69, 'tab_switch', 1, 3, '2025-04-19 16:43:43'),
(315, 'student1', 70, 'tab_switch', 1, 3, '2025-04-19 16:43:58'),
(316, 'student1', 71, 'tab_switch', 1, 3, '2025-04-19 17:26:19'),
(317, 'student1', 72, 'window_blur', 1, 2, '2025-04-19 18:55:32'),
(318, 'student1', 72, 'combined', 1, 10, '2025-04-19 18:55:32'),
(319, 'student1', 72, 'tab_switch', 1, 3, '2025-04-19 18:55:32'),
(320, 'student1', 72, 'window_blur', 2, 4, '2025-04-19 18:55:33'),
(321, 'student1', 72, 'combined', 2, 15, '2025-04-19 18:55:33'),
(322, 'student1', 72, 'tab_switch', 2, 5, '2025-04-19 18:55:33'),
(323, 'student1', 72, 'combined', 3, 20, '2025-04-19 18:55:33'),
(324, 'student1', 72, 'combined', 4, 20, '2025-04-19 18:55:35'),
(325, 'student1', 72, 'window_blur', 3, 6, '2025-04-19 18:55:35'),
(326, 'student1', 72, 'tab_switch', 3, 8, '2025-04-19 18:55:35'),
(327, 'student1', 72, 'combined', 5, 20, '2025-04-19 18:55:35'),
(328, 'student1', 72, 'tab_switch', 4, 15, '2025-04-19 18:55:38'),
(329, 'student1', 73, 'tab_switch', 1, 3, '2025-04-19 19:22:32'),
(330, 'student1', 74, 'window_blur', 1, 2, '2025-04-19 19:46:18'),
(331, 'student1', 74, 'window_blur', 2, 4, '2025-04-19 19:47:06'),
(332, 'student1', 74, 'tab_switch', 1, 3, '2025-04-19 19:47:12'),
(333, 'student2', 69, 'tab_switch', 1, 3, '2025-04-19 19:50:21'),
(334, 'student2', 70, 'window_blur', 1, 2, '2025-04-19 19:51:02'),
(335, 'student2', 70, 'window_blur', 2, 4, '2025-04-19 19:51:15'),
(336, 'student2', 70, 'window_blur', 3, 6, '2025-04-19 19:51:17'),
(337, 'student2', 70, 'combined', 1, 10, '2025-04-19 19:51:17'),
(338, 'student2', 70, 'tab_switch', 1, 3, '2025-04-19 19:51:17'),
(339, 'student2', 70, 'window_blur', 4, 8, '2025-04-19 19:52:02'),
(340, 'student2', 70, 'combined', 2, 15, '2025-04-19 19:52:02'),
(341, 'student2', 70, 'tab_switch', 2, 5, '2025-04-19 19:52:02'),
(342, 'student2', 70, 'combined', 3, 20, '2025-04-19 19:52:03'),
(343, 'student2', 70, 'window_blur', 5, 8, '2025-04-19 19:52:03'),
(344, 'student2', 70, 'tab_switch', 3, 8, '2025-04-19 19:52:03'),
(345, 'student2', 70, 'combined', 4, 20, '2025-04-19 19:52:03'),
(346, 'student2', 70, 'tab_switch', 4, 15, '2025-04-19 19:54:41'),
(347, 'student2', 74, 'tab_switch', 1, 3, '2025-04-19 19:55:23'),
(348, 'student2', 71, 'tab_switch', 1, 3, '2025-04-19 19:55:32'),
(349, 'student2', 75, 'window_blur', 1, 2, '2025-04-19 19:57:13'),
(350, 'student2', 75, 'window_blur', 2, 4, '2025-04-19 19:57:25'),
(351, 'student2', 75, 'tab_switch', 1, 3, '2025-04-19 19:57:28'),
(352, 'student2', 76, 'tab_switch', 1, 3, '2025-04-19 20:03:16'),
(353, 'student3', 76, 'tab_switch', 1, 3, '2025-04-19 20:15:03'),
(354, 'student3', 77, 'window_blur', 1, 2, '2025-04-19 20:15:36'),
(355, 'student3', 77, 'window_blur', 2, 4, '2025-04-19 20:15:37'),
(356, 'student3', 77, 'window_blur', 3, 6, '2025-04-19 20:15:38'),
(357, 'student3', 77, 'window_blur', 4, 8, '2025-04-19 20:15:40'),
(358, 'student3', 77, 'window_blur', 5, 8, '2025-04-19 20:15:47'),
(359, 'student3', 77, 'window_blur', 6, 8, '2025-04-19 20:15:48'),
(360, 'student3', 77, 'window_blur', 7, 8, '2025-04-19 20:15:50'),
(361, 'student3', 77, 'window_blur', 8, 8, '2025-04-19 20:15:57'),
(362, 'student3', 77, 'window_blur', 9, 8, '2025-04-19 20:16:00'),
(363, 'student3', 77, 'tab_switch', 1, 3, '2025-04-19 20:16:07'),
(364, 'student2', 77, 'tab_switch', 1, 3, '2025-04-19 20:26:06'),
(365, 'student2', 78, 'window_blur', 1, 2, '2025-04-19 20:30:52'),
(366, 'student2', 78, 'tab_switch', 1, 3, '2025-04-19 20:30:55'),
(367, 'student1', 80, 'window_blur', 1, 2, '2025-04-19 20:43:39'),
(368, 'student1', 80, 'window_blur', 2, 4, '2025-04-19 20:43:40'),
(369, 'student1', 80, 'window_blur', 3, 6, '2025-04-19 20:43:41'),
(370, 'student1', 80, 'tab_switch', 1, 3, '2025-04-19 20:43:57'),
(371, 'student2', 80, 'window_blur', 1, 2, '2025-04-19 20:49:08'),
(372, 'student2', 80, 'combined', 1, 10, '2025-04-19 20:49:08'),
(373, 'student2', 80, 'tab_switch', 1, 3, '2025-04-19 20:49:08'),
(374, 'student2', 80, 'combined', 2, 15, '2025-04-19 20:49:10'),
(375, 'student2', 80, 'window_blur', 2, 4, '2025-04-19 20:49:10'),
(376, 'student2', 80, 'combined', 3, 20, '2025-04-19 20:49:10'),
(377, 'student2', 80, 'tab_switch', 2, 5, '2025-04-19 20:49:10'),
(378, 'student2', 80, 'tab_switch', 3, 8, '2025-04-19 20:49:18'),
(379, 'student3', 80, 'tab_switch', 1, 3, '2025-04-19 20:52:56'),
(380, 'student3', 78, 'tab_switch', 1, 3, '2025-04-19 21:18:31');

-- --------------------------------------------------------

--
-- Table structure for table `exm_list`
--

CREATE TABLE `exm_list` (
  `exid` int(100) NOT NULL,
  `exname` varchar(100) NOT NULL,
  `nq` int(50) NOT NULL,
  `desp` varchar(200) DEFAULT NULL,
  `subt` datetime NOT NULL,
  `extime` datetime NOT NULL,
  `datetime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `subject` varchar(100) NOT NULL,
  `duration` int(11) DEFAULT 60
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

CREATE TABLE `message` (
  `id` int(11) NOT NULL,
  `fname` varchar(100) NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `feedback` varchar(1000) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message`
--

INSERT INTO `message` (`id`, `fname`, `date`, `feedback`) VALUES
(5, 'Teacher Rosey', '2021-12-12 13:01:00', 'Please kindly complete all the homework and submit tomorrow '),
(6, 'Teacher Rosey', '2021-12-13 06:23:18', 'Hello this is an annoucement'),
(9, 'Jack Rosso', '2025-04-14 18:05:13', 'hello guys'),
(10, 'Jack Rosso', '2025-04-16 15:21:41', 'lmao'),
(11, 'Jack Rosso', '2025-04-19 07:10:23', 'pdhai kro'),
(12, 'Jack Rosso', '2025-04-19 09:56:02', 'hello students'),
(13, 'Jack Rosso', '2025-04-19 09:59:31', 'hello');

-- --------------------------------------------------------

--
-- Table structure for table `mock_atmpt_list`
--

CREATE TABLE `mock_atmpt_list` (
  `id` int(100) NOT NULL,
  `mock_exid` int(100) NOT NULL,
  `uname` varchar(100) NOT NULL,
  `nq` int(100) NOT NULL,
  `cnq` int(100) NOT NULL,
  `ptg` int(100) NOT NULL,
  `status` int(10) NOT NULL,
  `integrity_score` int(3) NOT NULL DEFAULT 100,
  `subtime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `integrity_category` varchar(50) DEFAULT 'Good'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mock_cheat_violations`
--

CREATE TABLE `mock_cheat_violations` (
  `id` int(11) NOT NULL,
  `student_username` varchar(50) NOT NULL,
  `mock_exam_id` int(11) NOT NULL,
  `violation_type` varchar(50) NOT NULL,
  `occurrence` int(11) NOT NULL,
  `penalty` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mock_exm_list`
--

CREATE TABLE `mock_exm_list` (
  `mock_exid` int(100) NOT NULL,
  `original_exid` int(100) NOT NULL,
  `mock_number` int(2) NOT NULL,
  `exname` varchar(100) NOT NULL,
  `nq` int(50) NOT NULL DEFAULT 5,
  `desp` varchar(100) NOT NULL,
  `subt` datetime NOT NULL,
  `extime` datetime NOT NULL,
  `datetime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `subject` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `duration` int(11) DEFAULT 60
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mock_qstn_ans`
--

CREATE TABLE `mock_qstn_ans` (
  `id` int(11) NOT NULL,
  `mock_exid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `sno` int(11) NOT NULL,
  `ans` varchar(50) NOT NULL,
  `datetime` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mock_qstn_list`
--

CREATE TABLE `mock_qstn_list` (
  `mock_qid` int(11) NOT NULL,
  `mock_exid` int(11) NOT NULL,
  `qstn` varchar(200) NOT NULL,
  `qstn_o1` varchar(100) NOT NULL,
  `qstn_o2` varchar(100) NOT NULL,
  `qstn_o3` varchar(100) NOT NULL,
  `qstn_o4` varchar(100) NOT NULL,
  `qstn_ans` varchar(100) NOT NULL,
  `sno` int(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qstn_list`
--

CREATE TABLE `qstn_list` (
  `exid` int(11) NOT NULL,
  `qid` int(11) NOT NULL,
  `qstn` varchar(200) NOT NULL,
  `qstn_o1` varchar(100) NOT NULL,
  `qstn_o2` varchar(100) NOT NULL,
  `qstn_o3` varchar(100) NOT NULL,
  `qstn_o4` varchar(100) NOT NULL,
  `qstn_ans` varchar(100) NOT NULL,
  `sno` int(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qstn_list`
--

INSERT INTO `qstn_list` (`exid`, `qid`, `qstn`, `qstn_o1`, `qstn_o2`, `qstn_o3`, `qstn_o4`, `qstn_ans`, `sno`) VALUES
(75, 123, 'iaugdy', 'ygA8gsaUHU', 'HUISADH DUHASD HAI', 'SUIAD IAUH', 'HSIUHD AHD', 'HSIUHD AHD', 1),
(75, 124, 's fsoih dshfdohf', 'o sfhds ofdsof ', 'oshf odshf sohf d', 'ohsdf dhf osf so', 'o sdhfosd hfsoifh', 'o sdhfosd hfsoifh', 2),
(76, 125, 'oasdoiahsdoihs', 'obboih', 'ooihoij', 'oioihoioi', 'oiihoijioj', 'oiihoijioj', 1),
(76, 126, 'saknosabdoishdn dos dohasdo', 'oqwhdi', ';hph', 'hshodij', 'ijsdoaijs', 'ijsdoaijs', 2),
(77, 127, 'oasdoiahsdoihs', 'obboih', 'sadasd', 'sadasd', 'asdas', 'asdas', 1),
(77, 128, 'saknosabdoishdn dos dohasdo', 'oqwhdi', 'iohaosihddi', '9..', 'pisfhkm32', 'pooijhp', 2),
(78, 129, 'iytuc', 'tcuc', 'vuyv', 'vyu', 'yvuvuyv', 'yvuvuyv', 1),
(78, 130, 'safs afa f', 'iyvuc', 'uc', 'uv', 'iviviu', 'iviviu', 2),
(80, 131, 'Which of the following is a primary alcohol?', '2-propanol', '1-butanol', '2-butanol', 'tert-butanol', '1-butanol', 1),
(80, 132, 'What is the IUPAC name of CH₃CH₂CH₂OH?', 'Methanol', 'Ethanol', 'Propanol', 'Butanol', 'Propanol', 2),
(80, 133, 'Which test is used to distinguish between primary, secondary, and tertiary alcohols?', 'Benedict’s test', 'Lucas test', 'Tollen’s test', 'Fehling’s test', 'Lucas test', 3),
(80, 134, 'Which of the following alcohols is least soluble in water?', 'Methanol', 'Ethanol', '1-butanol', '1-decanol', '1-decanol', 4),
(80, 135, 'What happens when a primary alcohol is oxidized?', 'It forms a ketone', 'It forms an alkene', 'It forms an aldehyde or carboxylic acid', 'It remains unchanged', 'It forms an aldehyde or carboxylic acid', 5);

-- --------------------------------------------------------

--
-- Table structure for table `question_options`
--

CREATE TABLE `question_options` (
  `id` int(11) NOT NULL,
  `qid` int(11) NOT NULL,
  `option_text` varchar(100) NOT NULL,
  `option_number` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `read_messages`
--

CREATE TABLE `read_messages` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `uname` varchar(100) NOT NULL,
  `read_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `read_messages`
--

INSERT INTO `read_messages` (`id`, `message_id`, `uname`, `read_date`) VALUES
(1, 5, 'student1', '2025-04-19 07:09:47'),
(2, 6, 'student1', '2025-04-19 07:09:47'),
(3, 9, 'student1', '2025-04-19 07:09:47'),
(4, 10, 'student1', '2025-04-19 07:09:47'),
(5, 5, 'student2', '2025-04-19 07:11:13'),
(6, 6, 'student2', '2025-04-19 07:11:13'),
(7, 9, 'student2', '2025-04-19 07:11:13'),
(8, 10, 'student2', '2025-04-19 07:11:13'),
(9, 11, 'student2', '2025-04-19 07:11:13'),
(40, 12, 'student2', '2025-04-19 09:56:11'),
(41, 5, 'student3', '2025-04-19 09:59:17'),
(42, 6, 'student3', '2025-04-19 09:59:17'),
(43, 9, 'student3', '2025-04-19 09:59:17'),
(44, 10, 'student3', '2025-04-19 09:59:17'),
(45, 11, 'student3', '2025-04-19 09:59:17'),
(46, 12, 'student3', '2025-04-19 09:59:17'),
(59, 13, 'student3', '2025-04-19 09:59:35'),
(66, 13, 'student2', '2025-04-19 10:10:39'),
(71, 11, 'student1', '2025-04-19 16:59:28'),
(72, 12, 'student1', '2025-04-19 16:59:28'),
(73, 13, 'student1', '2025-04-19 16:59:28');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `id` int(11) NOT NULL,
  `uname` varchar(100) NOT NULL,
  `pword` varchar(255) NOT NULL,
  `fname` char(100) NOT NULL,
  `dob` date NOT NULL,
  `gender` char(10) NOT NULL,
  `email` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`id`, `uname`, `pword`, `fname`, `dob`, `gender`, `email`) VALUES
(11, 'student2', 'ad6a280417a0f533d8b670c61667e1a0', 'Abhay', '1998-02-12', 'M', 'abhaychandru2005@gmail.com'),
(12, '1RV23CS192', 'ad6a280417a0f533d8b670c61667e1a0', 'Rajat', '1790-12-12', 'M', 'rajat.contacts05@gmail.com'),
(305, '1RV23CS191', 'ad6a280417a0f533d8b670c61667e1a0', 'Rahul', '1889-02-12', 'M', 'rahul.raikar2005@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `student_answers`
--

CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(100) NOT NULL,
  `exid` int(100) NOT NULL,
  `qid` int(11) NOT NULL,
  `uname` varchar(100) NOT NULL,
  `selected_option` varchar(100) NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `answer_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_answers`
--

INSERT INTO `student_answers` (`id`, `attempt_id`, `exid`, `qid`, `uname`, `selected_option`, `is_correct`, `answer_time`) VALUES
(1, 87, 68, 108, 'student2', '', 0, '2025-04-19 16:32:57'),
(2, 87, 68, 109, 'student2', '', 0, '2025-04-19 16:32:57'),
(3, 87, 68, 110, 'student2', '', 0, '2025-04-19 16:32:57'),
(4, 87, 68, 111, 'student2', '', 0, '2025-04-19 16:32:57'),
(5, 87, 68, 112, 'student2', 'pp saod pasd', 1, '2025-04-19 16:32:57'),
(6, 88, 68, 112, 'student3', 'pp saod pasd', 1, '2025-04-19 16:36:05'),
(7, 89, 70, 114, 'student3', 'psojdpa od', 1, '2025-04-19 16:39:03'),
(8, 92, 70, 113, 'student1', 'jkkjnkjnlk', 1, '2025-04-19 16:43:58'),
(9, 92, 70, 114, 'student1', 'psojdpa od', 1, '2025-04-19 16:43:58'),
(10, 93, 71, 115, 'student1', 'iiuuhho', 0, '2025-04-19 17:26:19'),
(11, 93, 71, 116, 'student1', 'sosihd osiahd ', 0, '2025-04-19 17:26:19'),
(12, 94, 72, 117, 'student1', '', 0, '2025-04-19 18:55:38'),
(13, 94, 72, 118, 'student1', '', 0, '2025-04-19 18:55:38'),
(14, 95, 73, 119, 'student1', '', 0, '2025-04-19 19:22:32'),
(15, 95, 73, 120, 'student1', '', 0, '2025-04-19 19:22:32'),
(16, 96, 74, 121, 'student1', '', 0, '2025-04-19 19:47:12'),
(17, 96, 74, 122, 'student1', '', 0, '2025-04-19 19:47:12'),
(18, 98, 70, 113, 'student2', '', 0, '2025-04-19 19:54:41'),
(19, 98, 70, 114, 'student2', '', 0, '2025-04-19 19:54:41'),
(20, 99, 74, 121, 'student2', '', 0, '2025-04-19 19:55:23'),
(21, 99, 74, 122, 'student2', '', 0, '2025-04-19 19:55:23'),
(22, 100, 71, 115, 'student2', '', 0, '2025-04-19 19:55:32'),
(23, 100, 71, 116, 'student2', '', 0, '2025-04-19 19:55:32'),
(24, 101, 75, 123, 'student2', '', 0, '2025-04-19 19:57:28'),
(25, 101, 75, 124, 'student2', '', 0, '2025-04-19 19:57:28'),
(26, 102, 76, 125, 'student2', 'ooihoij', 0, '2025-04-19 20:03:16'),
(27, 102, 76, 126, 'student2', 'ijsdoaijs', 1, '2025-04-19 20:03:16'),
(28, 103, 76, 125, 'student3', 'oiihoijioj', 1, '2025-04-19 20:15:02'),
(29, 103, 76, 126, 'student3', 'ijsdoaijs', 1, '2025-04-19 20:15:02'),
(30, 104, 77, 127, 'student3', '', 0, '2025-04-19 20:16:06'),
(31, 104, 77, 128, 'student3', '', 0, '2025-04-19 20:16:06'),
(32, 105, 77, 127, 'student2', 'sadasd', 0, '2025-04-19 20:26:06'),
(33, 105, 77, 128, 'student2', 'pisfhkm32', 0, '2025-04-19 20:26:06'),
(34, 106, 78, 129, 'student2', 'yvuvuyv', 1, '2025-04-19 20:30:55'),
(35, 106, 78, 130, 'student2', 'iviviu', 1, '2025-04-19 20:30:55'),
(36, 107, 80, 131, 'student1', '1-butanol', 1, '2025-04-19 20:43:56'),
(37, 107, 80, 132, 'student1', 'Propanol', 1, '2025-04-19 20:43:56'),
(38, 107, 80, 133, 'student1', 'Lucas test', 1, '2025-04-19 20:43:56'),
(39, 107, 80, 134, 'student1', '1-butanol', 0, '2025-04-19 20:43:56'),
(40, 107, 80, 135, 'student1', 'It remains unchanged', 0, '2025-04-19 20:43:56'),
(41, 108, 80, 131, 'student2', '2-butanol', 0, '2025-04-19 20:49:17'),
(42, 108, 80, 132, 'student2', 'Propanol', 1, '2025-04-19 20:49:17'),
(43, 108, 80, 133, 'student2', 'Lucas test', 1, '2025-04-19 20:49:17'),
(44, 108, 80, 134, 'student2', '1-butanol', 0, '2025-04-19 20:49:17'),
(45, 108, 80, 135, 'student2', 'It forms an aldehyde or carboxylic acid', 1, '2025-04-19 20:49:17'),
(46, 109, 80, 131, 'student3', '2-propanol', 0, '2025-04-19 20:52:56'),
(47, 109, 80, 132, 'student3', 'Propanol', 1, '2025-04-19 20:52:56'),
(48, 109, 80, 133, 'student3', 'Lucas test', 1, '2025-04-19 20:52:56'),
(49, 109, 80, 134, 'student3', 'Ethanol', 0, '2025-04-19 20:52:56'),
(50, 109, 80, 135, 'student3', 'It forms a ketone', 0, '2025-04-19 20:52:56'),
(51, 110, 78, 129, 'student3', 'yvuvuyv', 1, '2025-04-19 21:18:31'),
(52, 110, 78, 130, 'student3', 'uc', 0, '2025-04-19 21:18:31');

-- --------------------------------------------------------

--
-- Table structure for table `teacher`
--

CREATE TABLE `teacher` (
  `id` int(11) NOT NULL,
  `uname` varchar(100) NOT NULL,
  `pword` varchar(255) NOT NULL,
  `fname` char(100) NOT NULL,
  `dob` date NOT NULL,
  `gender` char(10) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher`
--

INSERT INTO `teacher` (`id`, `uname`, `pword`, `fname`, `dob`, `gender`, `email`, `subject`) VALUES
(1, 'teacher', 'a426dcf72ba25d046591f81a5495eab7', 'Ningappa', '2021-12-01', 'M', 'teacher@teach.com', 'CHEMISTRY');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `atmpt_list`
--
ALTER TABLE `atmpt_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certificate_nfts`
--
ALTER TABLE `certificate_nfts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attempt_id` (`attempt_id`);

--
-- Indexes for table `cheat_violations`
--
ALTER TABLE `cheat_violations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `exm_list`
--
ALTER TABLE `exm_list`
  ADD PRIMARY KEY (`exid`);

--
-- Indexes for table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mock_atmpt_list`
--
ALTER TABLE `mock_atmpt_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mock_cheat_violations`
--
ALTER TABLE `mock_cheat_violations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mock_exm_list`
--
ALTER TABLE `mock_exm_list`
  ADD PRIMARY KEY (`mock_exid`);

--
-- Indexes for table `mock_qstn_ans`
--
ALTER TABLE `mock_qstn_ans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mock_qstn_list`
--
ALTER TABLE `mock_qstn_list`
  ADD PRIMARY KEY (`mock_qid`);

--
-- Indexes for table `qstn_list`
--
ALTER TABLE `qstn_list`
  ADD PRIMARY KEY (`qid`);

--
-- Indexes for table `question_options`
--
ALTER TABLE `question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `qid` (`qid`);

--
-- Indexes for table `read_messages`
--
ALTER TABLE `read_messages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `message_user` (`message_id`,`uname`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exid` (`exid`),
  ADD KEY `qid` (`qid`),
  ADD KEY `attempt_id` (`attempt_id`);

--
-- Indexes for table `teacher`
--
ALTER TABLE `teacher`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `atmpt_list`
--
ALTER TABLE `atmpt_list`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `certificate_nfts`
--
ALTER TABLE `certificate_nfts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `cheat_violations`
--
ALTER TABLE `cheat_violations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=381;

--
-- AUTO_INCREMENT for table `exm_list`
--
ALTER TABLE `exm_list`
  MODIFY `exid` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `message`
--
ALTER TABLE `message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `mock_atmpt_list`
--
ALTER TABLE `mock_atmpt_list`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `mock_cheat_violations`
--
ALTER TABLE `mock_cheat_violations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

--
-- AUTO_INCREMENT for table `mock_exm_list`
--
ALTER TABLE `mock_exm_list`
  MODIFY `mock_exid` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `mock_qstn_ans`
--
ALTER TABLE `mock_qstn_ans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=169;

--
-- AUTO_INCREMENT for table `mock_qstn_list`
--
ALTER TABLE `mock_qstn_list`
  MODIFY `mock_qid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=291;

--
-- AUTO_INCREMENT for table `qstn_list`
--
ALTER TABLE `qstn_list`
  MODIFY `qid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT for table `question_options`
--
ALTER TABLE `question_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `read_messages`
--
ALTER TABLE `read_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=307;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `teacher`
--
ALTER TABLE `teacher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
