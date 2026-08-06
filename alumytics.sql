-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 07, 2026 at 03:12 PM
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
-- Database: `alumytics`
--

-- --------------------------------------------------------

--
-- Table structure for table `awards`
--

CREATE TABLE `awards` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `award_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `award_date` date NOT NULL,
  `award_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `award_title` varchar(255) DEFAULT NULL,
  `awarded_by` varchar(255) DEFAULT NULL,
  `award_year` varchar(10) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `awards`
--

INSERT INTO `awards` (`id`, `user_id`, `award_name`, `category`, `award_date`, `award_file`, `created_at`, `award_title`, `awarded_by`, `award_year`, `description`) VALUES
(15, 114, '', 'Innovation & Research', '2025-12-16', 'uploads/awards/award_69416f6481c63_att.QYJ9Zj29LqHN6aEDqOpkh2viN-r-OLAsyVmz--CaHqY.jpeg', '2025-12-16 09:44:29', 'BEST IN MICROSOFT OFFICES', 'CONCENTRIX INTERNATIONAL', NULL, ''),
(16, 115, '', 'Professional Achievement', '2021-12-10', 'uploads/awards/award_69416541daa55_here\'s aesthetic wallpaper!!.jpg', '2025-12-16 13:57:21', 'BEST IN LEISURE TIME', 'ABC TECH', NULL, ''),
(17, 116, '', 'Outstanding Performance', '2025-12-16', 'uploads/awards/award_69417528c32cb_inbound8874445812805471003.jpg', '2025-12-16 15:05:12', 'BEST IN AUDIT', 'BDO UNIBANK', NULL, ''),
(18, 117, '', 'Outstanding Performance', '2025-04-17', 'uploads/awards/award_69418699567ce_IMG_20251214_214956.jpg', '2025-12-16 16:12:30', 'OUTSTANDING EMPLOYEE OF THE YEAR', 'COMPANY', NULL, ''),
(19, 113, '', 'Professional Achievement', '2025-12-02', 'uploads/awards/award_6943612ae7f09_logo - Edited.png', '2025-12-18 02:04:26', 'TESTD2ERW', 'TEST', NULL, ''),
(20, 144, '', 'Outstanding Performance', '2026-01-04', 'uploads/awards/award_695a4040c69ff_Thesis - NQ.png', '2026-01-04 10:26:08', 'EMPLOYEE OF THE YEAR', 'VILLARICA', NULL, ''),
(21, 146, '', 'Professional Achievement', '2026-07-08', 'uploads/awards/award_6a48b970e412b_Untitled design.png', '2026-07-04 07:42:40', 'TEST', 'TEST', NULL, ''),
(22, 147, '', 'Professional Achievement', '2026-07-04', 'uploads/awards/award_6a4b58d4d8cb7_Untitled design.png', '2026-07-06 07:27:16', 'TEST', 'TEST', NULL, 'TEST');

-- --------------------------------------------------------

--
-- Table structure for table `campuses`
--

CREATE TABLE `campuses` (
  `id` int(11) NOT NULL,
  `university_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campuses`
--

INSERT INTO `campuses` (`id`, `university_id`, `name`, `address_line1`, `address_line2`, `city`, `province`, `region`, `postal_code`, `created_at`, `updated_at`) VALUES
(2, 2, 'San Pablo', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-24 21:45:24', '2025-12-02 12:00:58'),
(3, 3, 'Siniloan', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-04 18:13:52', '2026-01-04 18:13:52');

-- --------------------------------------------------------

--
-- Table structure for table `certifications`
--

CREATE TABLE `certifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `certification_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `issuing_body` varchar(255) DEFAULT NULL,
  `industry` varchar(100) NOT NULL,
  `certification_date` date NOT NULL,
  `certification_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certifications`
--

INSERT INTO `certifications` (`id`, `user_id`, `certification_name`, `category`, `issuing_body`, `industry`, `certification_date`, `certification_file`, `created_at`, `name`) VALUES
(17, 115, 'BEST EMPLOYEE OF THE YEAR', 'Technology', 'ABC TECH', '', '2023-12-18', 'uploads/certification/cert_694164ea9a1e1_here\'s aesthetic wallpaper!!.jpg', '2025-12-16 13:55:54', NULL),
(18, 114, 'IT TRAINING FOR RESILIENCY', 'Technology', 'CONCENTRIX COMPANY', '', '2025-12-16', 'uploads/certification/cert_69416f108312b_f1891280cc281e6d054f8f51e330acc1.jpeg', '2025-12-16 14:39:12', NULL),
(19, 116, 'AUDITING PARTICIPATION', 'Healthcare', 'BDO UNIBANK', '', '2025-12-16', 'uploads/certification/cert_694174ec62627_inbound2189055012560550956.jpg', '2025-12-16 15:04:12', NULL),
(21, 113, 'TRY', 'Education', 'TEST', '', '2025-12-07', 'uploads/certification/cert_694360fe40115_logo - Edited.png', '2025-12-18 02:03:42', NULL),
(22, 144, 'BEST IN AUDIT', 'Finance', 'VILLARICA', '', '2026-01-04', 'uploads/certification/cert_695a400b6438a_AgRo_Farm - SUS Positive Question.png', '2026-01-04 10:25:15', NULL),
(23, 146, 'IBM', 'Technology', 'TEST', '', '2026-07-15', 'uploads/certification/cert_6a48b5334355d_Untitled design.png', '2026-07-04 07:24:35', NULL),
(24, 147, 'IBM', 'Technology', 'TEST', '', '2026-07-08', 'uploads/certification/cert_6a4b58b748451_Untitled design.png', '2026-07-06 07:26:47', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colleges`
--

INSERT INTO `colleges` (`id`, `name`) VALUES
(20, 'College of Accountancy'),
(21, 'College of Arts and Sciences'),
(22, 'College of Computer Studies and Technology'),
(23, 'College of Business Administration'),
(24, 'College of Engineering'),
(25, 'College of Human Kinetics'),
(26, 'College of Nursing and Allied Health Sciences'),
(27, 'College of Teacher Education'),
(28, 'College of Tourism and Hospitality Management'),
(29, 'College of Computer Studies');

-- --------------------------------------------------------

--
-- Table structure for table `company_address`
--

CREATE TABLE `company_address` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `company_street_address` text DEFAULT NULL,
  `company_province` varchar(255) DEFAULT NULL,
  `company_city` varchar(255) DEFAULT NULL,
  `company_barangay` varchar(255) DEFAULT NULL,
  `company_zip_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_address`
--

INSERT INTO `company_address` (`id`, `user_id`, `company_street_address`, `company_province`, `company_city`, `company_barangay`, `company_zip_code`) VALUES
(27, 113, 'PLOT NO. 3A, SECTOR 126', 'UTTAR PRADESH', 'NOIDA', 'SECTOR 126', '4000'),
(28, 114, '0218 BLK 28', 'SHRINKEL', 'REPLEO', 'JELSEKI', '40002'),
(29, 115, 'MABINI', '043400000', '043428000', '043428020', '4000'),
(30, 116, '0293 BONIFACIO STREET', '037700000', '037708000', '037708009', '48021'),
(31, 117, '2450 HARVEST POINT BOULEVARD', '041000000', '041014000', '041014077', '97428'),
(32, 118, '', '', '', '', ''),
(34, 120, '', '', '', '', ''),
(35, 121, '', '', '', '', ''),
(36, 122, '', '', '', '', ''),
(37, 124, '', '', '', '', ''),
(38, 125, '', '', '', '', ''),
(39, 126, '', '', '', '', ''),
(40, 127, '', '', '', '', ''),
(41, 128, '18TH FLOOR, AURORA TOWER, AYALA AVENUE', '045800000', '045801000', '045801012', '2000'),
(42, 130, 'GROUND FLOOR, CALAMBA CENTRAL MALL, NATIONAL HIGHWAY', '043400000', '043405000', '043405049', '4027'),
(43, 129, 'NATIONAL HIGHWAY', '043400000', '043425000', '043425010', '4023'),
(44, 131, 'BLK 7892', '025700000', '025704000', '025704012', '4058'),
(45, 133, 'DALAHICAN ROAD', '045600000', '045624000', '045624020', '4301'),
(46, 134, 'SAN JOSE', '043400000', '043424000', '043424053', '4000'),
(47, 135, 'LOT 14, FIRST STREET, CARMELRAY INDUSTRIAL PARK 2', '043400000', '043405000', '043405011', '4028'),
(48, 136, 'KM 56 MARCOS HIGHWAY', '045800000', '045812000', '045812012', '1980'),
(49, 137, 'UNIT 402, PARKSIDE CORPORATE CENTER, NATIONAL HIGHWAY', '043400000', '043411000', '043411004', '4030'),
(50, 138, '2ND FLOOR, JVR BUILDING, QUEZON AVENUE', '045600000', '045624000', '045624017', '4301'),
(51, 139, 'ROBERT HALF INC.', '036900000', '036916000', '036916006', '4002'),
(52, 141, 'COLAGO AVENUE', '043400000', '043424000', '043424050', '4000'),
(53, 142, 'QUEZON AVENUE EXTENSION', '045600000', '045624000', '', '4301'),
(54, 143, 'CLOUDSTAFF SOLUTIONS INC.', '035400000', '035409000', '035409026', '2023'),
(55, 144, 'PLOT NO. 3A, SECTOR 126', '030800000', '030804000', '030804050', '4009'),
(56, 146, 'SAN PABLO STB', 'GGDFDFGFD', 'GDFGDF', 'GDFGFD', '3020'),
(57, 147, 'SAN PABLO STB', 'TEST', 'TEST', 'TEST', '3020');

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `code` char(2) NOT NULL,
  `name` varchar(100) NOT NULL,
  `has_states` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `code`, `name`, `has_states`) VALUES
(20, 'AF', 'Afghanistan', 1),
(21, 'AX', 'Aland Islands', 1),
(22, 'AL', 'Albania', 1),
(23, 'DZ', 'Algeria', 1),
(24, 'AS', 'American Samoa', 1),
(25, 'AD', 'Andorra', 1),
(26, 'AO', 'Angola', 1),
(27, 'AI', 'Anguilla', 1),
(28, 'AQ', 'Antarctica', 1),
(29, 'AG', 'Antigua and Barbuda', 1),
(30, 'AR', 'Argentina', 1),
(31, 'AM', 'Armenia', 1),
(32, 'AW', 'Aruba', 1),
(33, 'AU', 'Australia', 1),
(34, 'AT', 'Austria', 1),
(35, 'AZ', 'Azerbaijan', 1),
(36, 'BH', 'Bahrain', 1),
(37, 'BD', 'Bangladesh', 1),
(38, 'BB', 'Barbados', 1),
(39, 'BY', 'Belarus', 1),
(40, 'BE', 'Belgium', 1),
(41, 'BZ', 'Belize', 1),
(42, 'BJ', 'Benin', 1),
(43, 'BM', 'Bermuda', 1),
(44, 'BT', 'Bhutan', 1),
(45, 'BO', 'Bolivia', 1),
(46, 'BQ', 'Bonaire, Sint Eustatius and Saba', 1),
(47, 'BA', 'Bosnia and Herzegovina', 1),
(48, 'BW', 'Botswana', 1),
(49, 'BV', 'Bouvet Island', 1),
(50, 'BR', 'Brazil', 1),
(51, 'IO', 'British Indian Ocean Territory', 1),
(52, 'BN', 'Brunei', 1),
(53, 'BG', 'Bulgaria', 1),
(54, 'BF', 'Burkina Faso', 1),
(55, 'BI', 'Burundi', 1),
(56, 'KH', 'Cambodia', 1),
(57, 'CM', 'Cameroon', 1),
(58, 'CA', 'Canada', 1),
(59, 'CV', 'Cape Verde', 1),
(60, 'KY', 'Cayman Islands', 1),
(61, 'CF', 'Central African Republic', 1),
(62, 'TD', 'Chad', 1),
(63, 'CL', 'Chile', 1),
(64, 'CN', 'China', 1),
(65, 'CX', 'Christmas Island', 1),
(66, 'CC', 'Cocos (Keeling) Islands', 1),
(67, 'CO', 'Colombia', 1),
(68, 'KM', 'Comoros', 1),
(69, 'CG', 'Congo', 1),
(70, 'CK', 'Cook Islands', 1),
(71, 'CR', 'Costa Rica', 1),
(72, 'HR', 'Croatia', 1),
(73, 'CU', 'Cuba', 1),
(74, 'CW', 'Curaçao', 1),
(75, 'CY', 'Cyprus', 1),
(76, 'CZ', 'Czech Republic', 1),
(77, 'CD', 'Democratic Republic of the Congo', 1),
(78, 'DK', 'Denmark', 1),
(79, 'DJ', 'Djibouti', 1),
(80, 'DM', 'Dominica', 1),
(81, 'DO', 'Dominican Republic', 1),
(82, 'EC', 'Ecuador', 1),
(83, 'EG', 'Egypt', 1),
(84, 'SV', 'El Salvador', 1),
(85, 'GQ', 'Equatorial Guinea', 1),
(86, 'ER', 'Eritrea', 1),
(87, 'EE', 'Estonia', 1),
(88, 'SZ', 'Eswatini', 1),
(89, 'ET', 'Ethiopia', 1),
(90, 'FK', 'Falkland Islands', 1),
(91, 'FO', 'Faroe Islands', 1),
(92, 'FJ', 'Fiji Islands', 1),
(93, 'FI', 'Finland', 1),
(94, 'FR', 'France', 1),
(95, 'GF', 'French Guiana', 1),
(96, 'PF', 'French Polynesia', 1),
(97, 'TF', 'French Southern Territories', 1),
(98, 'GA', 'Gabon', 1),
(99, 'GE', 'Georgia', 1),
(100, 'DE', 'Germany', 1),
(101, 'GH', 'Ghana', 1),
(102, 'GI', 'Gibraltar', 1),
(103, 'GR', 'Greece', 1),
(104, 'GL', 'Greenland', 1),
(105, 'GD', 'Grenada', 1),
(106, 'GP', 'Guadeloupe', 1),
(107, 'GU', 'Guam', 1),
(108, 'GT', 'Guatemala', 1),
(109, 'GG', 'Guernsey', 1),
(110, 'GN', 'Guinea', 1),
(111, 'GW', 'Guinea-Bissau', 1),
(112, 'GY', 'Guyana', 1),
(113, 'HT', 'Haiti', 1),
(114, 'HM', 'Heard Island and McDonald Islands', 1),
(115, 'HN', 'Honduras', 1),
(116, 'HK', 'Hong Kong S.A.R.', 0),
(117, 'HU', 'Hungary', 1),
(118, 'IS', 'Iceland', 1),
(119, 'IN', 'India', 1),
(120, 'ID', 'Indonesia', 1),
(121, 'IR', 'Iran', 1),
(122, 'IQ', 'Iraq', 1),
(123, 'IE', 'Ireland', 1),
(124, 'IL', 'Israel', 1),
(125, 'IT', 'Italy', 1),
(126, 'CI', 'Ivory Coast', 1),
(127, 'JM', 'Jamaica', 1),
(128, 'JP', 'Japan', 1),
(129, 'JE', 'Jersey', 1),
(130, 'JO', 'Jordan', 1),
(131, 'KZ', 'Kazakhstan', 1),
(132, 'KE', 'Kenya', 1),
(133, 'KI', 'Kiribati', 1),
(134, 'XK', 'Kosovo', 1),
(135, 'KW', 'Kuwait', 1),
(136, 'KG', 'Kyrgyzstan', 1),
(137, 'LA', 'Laos', 1),
(138, 'LV', 'Latvia', 1),
(139, 'LB', 'Lebanon', 1),
(140, 'LS', 'Lesotho', 1),
(141, 'LR', 'Liberia', 1),
(142, 'LY', 'Libya', 1),
(143, 'LI', 'Liechtenstein', 1),
(144, 'LT', 'Lithuania', 1),
(145, 'LU', 'Luxembourg', 1),
(146, 'MO', 'Macau S.A.R.', 1),
(147, 'MG', 'Madagascar', 1),
(148, 'MW', 'Malawi', 1),
(149, 'MY', 'Malaysia', 1),
(150, 'MV', 'Maldives', 1),
(151, 'ML', 'Mali', 1),
(152, 'MT', 'Malta', 1),
(153, 'IM', 'Man (Isle of)', 1),
(154, 'MH', 'Marshall Islands', 1),
(155, 'MQ', 'Martinique', 1),
(156, 'MR', 'Mauritania', 1),
(157, 'MU', 'Mauritius', 1),
(158, 'YT', 'Mayotte', 1),
(159, 'MX', 'Mexico', 1),
(160, 'FM', 'Micronesia', 1),
(161, 'MD', 'Moldova', 1),
(162, 'MC', 'Monaco', 1),
(163, 'MN', 'Mongolia', 1),
(164, 'ME', 'Montenegro', 1),
(165, 'MS', 'Montserrat', 1),
(166, 'MA', 'Morocco', 1),
(167, 'MZ', 'Mozambique', 1),
(168, 'MM', 'Myanmar', 1),
(169, 'NA', 'Namibia', 1),
(170, 'NR', 'Nauru', 1),
(171, 'NP', 'Nepal', 1),
(172, 'NL', 'Netherlands', 1),
(173, 'NC', 'New Caledonia', 1),
(174, 'NZ', 'New Zealand', 1),
(175, 'NI', 'Nicaragua', 1),
(176, 'NE', 'Niger', 1),
(177, 'NG', 'Nigeria', 1),
(178, 'NU', 'Niue', 1),
(179, 'NF', 'Norfolk Island', 1),
(180, 'KP', 'North Korea', 1),
(181, 'MK', 'North Macedonia', 1),
(182, 'MP', 'Northern Mariana Islands', 1),
(183, 'NO', 'Norway', 1),
(184, 'OM', 'Oman', 1),
(185, 'PK', 'Pakistan', 1),
(186, 'PW', 'Palau', 1),
(187, 'PS', 'Palestinian Territory Occupied', 1),
(188, 'PA', 'Panama', 1),
(189, 'PG', 'Papua New Guinea', 1),
(190, 'PY', 'Paraguay', 1),
(191, 'PE', 'Peru', 1),
(192, 'PH', 'Philippines', 1),
(193, 'PN', 'Pitcairn Island', 1),
(194, 'PL', 'Poland', 1),
(195, 'PT', 'Portugal', 1),
(196, 'PR', 'Puerto Rico', 1),
(197, 'QA', 'Qatar', 1),
(198, 'RE', 'Reunion', 1),
(199, 'RO', 'Romania', 1),
(200, 'RU', 'Russia', 1),
(201, 'RW', 'Rwanda', 1),
(202, 'SH', 'Saint Helena', 1),
(203, 'KN', 'Saint Kitts and Nevis', 1),
(204, 'LC', 'Saint Lucia', 1),
(205, 'PM', 'Saint Pierre and Miquelon', 1),
(206, 'VC', 'Saint Vincent and the Grenadines', 1),
(207, 'BL', 'Saint-Barthelemy', 1),
(208, 'MF', 'Saint-Martin (French part)', 1),
(209, 'WS', 'Samoa', 1),
(210, 'SM', 'San Marino', 1),
(211, 'ST', 'Sao Tome and Principe', 1),
(212, 'SA', 'Saudi Arabia', 1),
(213, 'SN', 'Senegal', 1),
(214, 'RS', 'Serbia', 1),
(215, 'SC', 'Seychelles', 1),
(216, 'SL', 'Sierra Leone', 1),
(217, 'SG', 'Singapore', 0),
(218, 'SX', 'Sint Maarten (Dutch part)', 1),
(219, 'SK', 'Slovakia', 1),
(220, 'SI', 'Slovenia', 1),
(221, 'SB', 'Solomon Islands', 1),
(222, 'SO', 'Somalia', 1),
(223, 'ZA', 'South Africa', 1),
(224, 'GS', 'South Georgia', 1),
(225, 'KR', 'South Korea', 1),
(226, 'SS', 'South Sudan', 1),
(227, 'ES', 'Spain', 1),
(228, 'LK', 'Sri Lanka', 1),
(229, 'SD', 'Sudan', 1),
(230, 'SR', 'Suriname', 1),
(231, 'SJ', 'Svalbard and Jan Mayen Islands', 1),
(232, 'SE', 'Sweden', 1),
(233, 'CH', 'Switzerland', 1),
(234, 'SY', 'Syria', 1),
(235, 'TW', 'Taiwan', 1),
(236, 'TJ', 'Tajikistan', 1),
(237, 'TZ', 'Tanzania', 1),
(238, 'TH', 'Thailand', 1),
(239, 'BS', 'The Bahamas', 1),
(240, 'GM', 'The Gambia', 1),
(241, 'TL', 'Timor-Leste', 1),
(242, 'TG', 'Togo', 1),
(243, 'TK', 'Tokelau', 1),
(244, 'TO', 'Tonga', 1),
(245, 'TT', 'Trinidad and Tobago', 1),
(246, 'TN', 'Tunisia', 1),
(247, 'TR', 'Turkey', 1),
(248, 'TM', 'Turkmenistan', 1),
(249, 'TC', 'Turks and Caicos Islands', 1),
(250, 'TV', 'Tuvalu', 1),
(251, 'UG', 'Uganda', 1),
(252, 'UA', 'Ukraine', 1),
(253, 'AE', 'United Arab Emirates', 1),
(254, 'GB', 'United Kingdom', 1),
(255, 'US', 'United States', 1),
(256, 'UM', 'United States Minor Outlying Islands', 1),
(257, 'UY', 'Uruguay', 1),
(258, 'UZ', 'Uzbekistan', 1),
(259, 'VU', 'Vanuatu', 1),
(260, 'VA', 'Vatican City State (Holy See)', 1),
(261, 'VE', 'Venezuela', 1),
(262, 'VN', 'Vietnam', 1),
(263, 'VG', 'Virgin Islands (British)', 1),
(264, 'VI', 'Virgin Islands (US)', 1),
(265, 'WF', 'Wallis and Futuna Islands', 1),
(266, 'EH', 'Western Sahara', 1),
(267, 'YE', 'Yemen', 1),
(268, 'ZM', 'Zambia', 1),
(269, 'ZW', 'Zimbabwe', 1);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `university_id` int(11) NOT NULL,
  `campus_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `university_id`, `campus_id`, `name`, `code`, `created_at`, `updated_at`) VALUES
(11, 2, 2, 'College of Accountancy', NULL, '2025-12-15 04:06:11', '2025-12-15 04:06:11'),
(12, 2, 2, 'College of Arts and Sciences', NULL, '2025-12-15 04:06:33', '2025-12-15 04:06:33'),
(13, 2, 2, 'College of Computer Studies and Technology', NULL, '2025-12-15 04:06:49', '2025-12-15 04:06:49'),
(14, 2, 2, 'College of Business Administration', NULL, '2025-12-15 04:06:59', '2025-12-15 04:06:59'),
(15, 2, 2, 'College of Engineering', NULL, '2025-12-15 04:07:09', '2025-12-15 04:07:09'),
(17, 2, 2, 'College of Human Kinetics', NULL, '2025-12-15 04:07:38', '2025-12-15 04:07:38'),
(18, 2, 2, 'College of Nursing and Allied Health Sciences', NULL, '2025-12-15 04:07:52', '2025-12-15 04:07:52'),
(19, 2, 2, 'College of Teacher Education', NULL, '2025-12-15 04:08:01', '2025-12-15 04:08:01'),
(20, 2, 2, 'College of Tourism and Hospitality Management', NULL, '2025-12-15 04:08:16', '2025-12-15 04:08:16'),
(21, 3, 3, 'College of Computer Studies', NULL, '2026-01-04 18:14:23', '2026-01-04 18:14:23');

-- --------------------------------------------------------

--
-- Table structure for table `education`
--

CREATE TABLE `education` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `school_university` varchar(255) NOT NULL,
  `campus_branch` varchar(255) NOT NULL,
  `college_department` varchar(255) NOT NULL,
  `program` varchar(255) NOT NULL,
  `major_specialization` varchar(255) DEFAULT NULL,
  `alumni_id` varchar(100) NOT NULL,
  `student_number` varchar(100) DEFAULT NULL,
  `campus` varchar(255) DEFAULT NULL,
  `branch` varchar(255) DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `year_graduated` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `education`
--

INSERT INTO `education` (`id`, `user_id`, `school_university`, `campus_branch`, `college_department`, `program`, `major_specialization`, `alumni_id`, `student_number`, `campus`, `branch`, `college`, `department`, `specialization`, `year_graduated`) VALUES
(44, 113, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF COMPUTER STUDIES AND TECHNOLOGY', 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', 'SERVICE MANAGEMENT PROGRAM', '5676542358', NULL, NULL, NULL, NULL, NULL, NULL, '2009'),
(45, 114, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF COMPUTER STUDIES AND TECHNOLOGY', 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', 'SERVICE MANAGEMENT PROGRAM', '1234566', NULL, NULL, NULL, NULL, NULL, NULL, '2018'),
(46, 115, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF COMPUTER STUDIES AND TECHNOLOGY', 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', 'SMP', '032219511', NULL, NULL, NULL, NULL, NULL, NULL, '2020'),
(47, 116, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF ACCOUNTANCY', 'BACHELOR OF SCIENCE IN ACCOUNTANCY', 'ACCOUNTANCY', '1234567', NULL, NULL, NULL, NULL, NULL, NULL, '2020'),
(48, 117, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF COMPUTER STUDIES AND TECHNOLOGY', 'BACHELOR OF SCIENCE IN INFORMATION SYSTEMS', 'INFO SYSTEMS', '1235567', NULL, NULL, NULL, NULL, NULL, NULL, '2025'),
(49, 118, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF TEACHER EDUCATION', 'BACHELOR OF SECONDARY EDUCATION', 'FILIPINO', '2208198', NULL, NULL, NULL, NULL, NULL, NULL, '2025'),
(51, 120, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF TEACHER EDUCATION', 'BACHELOR OF SECONDARY EDUCATION', 'SCIENCE', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025'),
(52, 121, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF TEACHER EDUCATION', 'BACHELOR OF ELEMENTARY EDUCATION', 'GENERALIST', 'EKIRA_060401', NULL, NULL, NULL, NULL, NULL, NULL, '2025'),
(53, 122, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF TEACHER EDUCATION', 'BACHELOR OF ELEMENTARY EDUCATION', 'GENERALIST', 'CASEY0827', NULL, NULL, NULL, NULL, NULL, NULL, '2025'),
(54, 123, '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(55, 124, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF ACCOUNTANCY', 'BACHELOR OF SCIENCE IN ACCOUNTING INFORMATION SYSTEM', 'ACCOUNTING INFORMATION SYSTEM', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025'),
(56, 125, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF ACCOUNTANCY', 'BACHELOR OF SCIENCE IN ACCOUNTANCY', 'ACCOUNTANCY', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025'),
(57, 126, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF BUSINESS ADMINISTRATION', 'BACHELOR OF SCIENCE IN ENTREPRENEURSHIP', 'N/A', '', NULL, NULL, NULL, NULL, NULL, NULL, '2026'),
(58, 127, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF ARTS AND SCIENCES', 'BACHELOR OF ARTS IN POLITICAL SCIENCE', 'N/A', '', NULL, NULL, NULL, NULL, NULL, NULL, '2026'),
(59, 128, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF COMPUTER STUDIES AND TECHNOLOGY', 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', 'SYSTEMS DEVELOPMENT AND NETWORK ADMINISTRATION', '', NULL, NULL, NULL, NULL, NULL, NULL, '2020'),
(60, 129, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF BUSINESS ADMINISTRATION', 'BACHELOR OF SCIENCE IN ENTREPRENEURSHIP', 'SBM', '20180021', NULL, NULL, NULL, NULL, NULL, NULL, '2018'),
(61, 130, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF BUSINESS ADMINISTRATION', 'BACHELOR OF SCIENCE IN BUSINESS ADMINISTRATION', 'MARKETING MANAGEMENT', '', NULL, NULL, NULL, NULL, NULL, NULL, '2021'),
(62, 131, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF ENGINEERING', 'BACHELOR OF SCIENCE IN COMPUTER ENGINEERING', 'COMPUTER MECHANICS', '', NULL, NULL, NULL, NULL, NULL, NULL, '2016'),
(63, 132, '', '', '', '', '', '20190033', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(64, 133, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF BUSINESS ADMINISTRATION', 'BACHELOR OF SCIENCE IN BUSINESS ADMINISTRATION', 'MARKETING MANAGEMENT', '20200049', NULL, NULL, NULL, NULL, NULL, NULL, '2019'),
(65, 134, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF TEACHER EDUCATION', 'BACHELOR OF SECONDARY EDUCATION', 'ENGLISH', '', NULL, NULL, NULL, NULL, NULL, NULL, '2019'),
(66, 135, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF ENGINEERING', 'BACHELOR OF SCIENCE IN COMPUTER ENGINEERING', 'EMBEDDED SYSTEMS AND INDUSTRIAL COMPUTING', '', NULL, NULL, NULL, NULL, NULL, NULL, '2018'),
(67, 136, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF TOURISM AND HOSPITALITY MANAGEMENT', 'BACHELOR OF SCIENCE IN HOSPITALITY MANAGEMENT', 'HOTEL AND RESTAURANT MANAGEMENT', '', NULL, NULL, NULL, NULL, NULL, NULL, '2017'),
(68, 137, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF ACCOUNTANCY', 'BACHELOR OF SCIENCE IN ACCOUNTANCY', 'FINANCIAL ACCOUNTING AND AUDITING', '', NULL, NULL, NULL, NULL, NULL, NULL, '2016'),
(69, 138, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF ARTS AND SCIENCES', 'BACHELOR OF SCIENCE IN PSYCHOLOGY', 'INDUSTRIAL AND ORGANIZATIONAL PSYCHOLOGY', '', NULL, NULL, NULL, NULL, NULL, NULL, '2015'),
(70, 139, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF BUSINESS ADMINISTRATION', 'BACHELOR OF SCIENCE IN OFFICE ADMINISTRATION', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '2019'),
(71, 140, '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(72, 141, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF TEACHER EDUCATION', 'BACHELOR OF SECONDARY EDUCATION', 'ENGLISH', '20170198', NULL, NULL, NULL, NULL, NULL, NULL, '2017'),
(73, 142, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF TOURISM AND HOSPITALITY MANAGEMENT', 'BACHELOR OF SCIENCE IN HOSPITALITY MANAGEMENT', 'HOTEL AND RESTAURANT SERVICES', '20200589', NULL, NULL, NULL, NULL, NULL, NULL, '2020'),
(74, 143, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF COMPUTER STUDIES AND TECHNOLOGY', 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', 'SOFTWARE DEVELOPMENT', '20180369', NULL, NULL, NULL, NULL, NULL, NULL, '2018'),
(75, 144, 'PAMANTASAN NG LUNGSOD NG SAN PABLO', 'SAN PABLO', 'COLLEGE OF ACCOUNTANCY', 'BACHELOR OF SCIENCE IN ACCOUNTANCY', 'ACCOUNTANCY', '', NULL, NULL, NULL, NULL, NULL, NULL, '2015'),
(77, 146, 'LAGUNA STATE POLYTECHNIC UNIVERSITY', 'SINILOAN', 'COLLEGE OF COMPUTER STUDIES', 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', 'SMP', '12654234', NULL, NULL, NULL, NULL, NULL, NULL, '2011'),
(78, 147, 'LAGUNA STATE POLYTECHNIC UNIVERSITY', 'SINILOAN', 'COLLEGE OF COMPUTER STUDIES', 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', 'SMP', '76342312', NULL, NULL, NULL, NULL, NULL, NULL, '2011');

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_codes`
--

CREATE TABLE `email_verification_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verification_codes`
--

INSERT INTO `email_verification_codes` (`id`, `user_id`, `code_hash`, `expires_at`, `verified_at`, `created_at`) VALUES
(13, 113, 'b84a7aec1ff5efe9a5418413423a055438d799d0629def4703477b8d38bd2a84', '2025-12-15 18:38:03', '2025-12-15 18:08:21', '2025-12-16 02:08:03'),
(14, 114, '5ebbeeb066fc7434a986a9994af7bcd896fbf665e4240a5b35ba6705054d4910', '2025-12-16 10:05:53', '2025-12-16 09:36:16', '2025-12-16 17:35:53'),
(15, 115, '4a5934a90cb4ba52746173e21ca68772f816f294d19aa4db3f7906a524e70e61', '2025-12-16 14:14:50', '2025-12-16 13:45:58', '2025-12-16 21:44:50'),
(16, 116, '6f3b39658b7362a3f194edd5589db7d4c42b8963cfbe7602ff3b2bd2c4eff0d3', '2025-12-16 15:28:57', '2025-12-16 14:59:44', '2025-12-16 22:58:57'),
(17, 117, '957d634a5c00a9ea99a83dc03be572aa046bfb452020ce2cba2bb2e54516d962', '2025-12-16 16:31:30', '2025-12-16 16:02:18', '2025-12-17 00:01:30'),
(18, 118, '07e24a6859720a8cd77985c62e957aac2da4cdf8475fce7fee2810087e511677', '2025-12-28 05:10:11', '2025-12-28 04:41:01', '2025-12-28 12:40:11'),
(20, 120, '0a069b57565feac36a88068d12db5cc61d360f3739e4ae271fc7dca3185df129', '2025-12-29 00:29:40', '2025-12-29 00:00:17', '2025-12-29 07:59:40'),
(21, 121, '32c93d65560b47dff198e1cd6e7f4d7577cb70df5097a27d4351691ccd53d723', '2025-12-30 13:25:37', '2025-12-30 12:56:29', '2025-12-30 20:55:37'),
(22, 122, '705fb876564d0e2a06d5a062e9701198b964aa95387443168edecde5533740a3', '2025-12-30 13:55:24', '2025-12-30 13:25:50', '2025-12-30 21:25:24'),
(23, 123, '51a1dab0efa036d8b1429d0cbd28dbbb7ca1cbc85c7bddd82b81ac5881021058', '2025-12-31 02:48:30', '2025-12-31 02:18:54', '2025-12-31 10:18:30'),
(24, 124, '4576d97bc82099f96261458618eee1e419a9043caa409c43772b4f73c8895c5e', '2025-12-31 06:36:46', '2025-12-31 06:07:14', '2025-12-31 14:06:46'),
(25, 125, 'a63d0c58c6dd8629a806ec034f900779b38836f1415715aed617267dc104fc0f', '2026-01-01 15:18:59', '2026-01-01 14:49:35', '2026-01-01 22:48:59'),
(26, 126, 'cb8a682108f7575eeb2d4fab5ed0648b81d094423c6985d549eeb63e62461d27', '2026-01-02 00:05:55', '2026-01-01 23:36:15', '2026-01-02 07:35:55'),
(27, 127, 'be76939c92e4a2a3eef971524572debfc3012fa582e03e46f32d2895e522864e', '2026-01-02 00:23:28', '2026-01-01 23:53:59', '2026-01-02 07:53:28'),
(28, 128, '998c7b54e63b42dc0201d221f3894858368583208677c6ccab5abe023f691b10', '2026-01-02 08:32:42', '2026-01-02 08:03:17', '2026-01-02 16:02:42'),
(29, 129, '0555d8bbf2d526681ef6c9933b7925a3e54554f1fb653144b7007557e2a00440', '2026-01-02 11:51:59', '2026-01-02 11:23:00', '2026-01-02 19:21:59'),
(30, 130, '4911fe1d716a03a9d5f18666818f46d153e48c8e66c8856d386dbe7a56c71994', '2026-01-02 12:15:56', '2026-01-02 11:46:43', '2026-01-02 19:45:56'),
(31, 131, 'f17061a3b3085cf98dbe6ef3034aabe2c608d091459f63ff1abf23c86a3f4712', '2026-01-02 12:25:06', '2026-01-02 11:55:24', '2026-01-02 19:55:06'),
(32, 132, '94946a86e85436f835a2056a39a61e86d02e959a905bf229583a14d10865ea03', '2026-01-02 12:32:21', NULL, '2026-01-02 20:02:21'),
(33, 133, '7f4c25a5e860761fd979413346ed6254e903a039514d810b58ea7efdaff44b97', '2026-01-02 12:38:47', '2026-01-02 12:09:05', '2026-01-02 20:08:47'),
(34, 134, 'afb7fc17e144ceb9dcef7c4f762e6997a317a4a88a130a258be9d96681422b5c', '2026-01-02 13:22:26', '2026-01-02 12:52:55', '2026-01-02 20:52:26'),
(35, 135, '918ea19dc22f0395466db497d04714d04f5b2c75324faee6e473c5b40ccc6390', '2026-01-02 13:38:51', '2026-01-02 13:09:57', '2026-01-02 21:08:51'),
(36, 136, '015c2bca92d1514903d211572d416718ccd6b988ec736e215e617e7e0f62fad8', '2026-01-02 14:00:43', '2026-01-02 13:31:04', '2026-01-02 21:30:43'),
(37, 137, '3a950a828826f1ae37f0c8d390904ecd77590ec090f04bc4dd35b0fef28e1a2b', '2026-01-02 14:10:46', '2026-01-02 13:41:05', '2026-01-02 21:40:46'),
(38, 138, '4b6d601c590721ad61b8af02591879c3a08c6545d542183cf9552ee4b91321ad', '2026-01-02 14:17:00', '2026-01-02 13:47:22', '2026-01-02 21:47:00'),
(39, 139, '873e7281b847b4674efab529afd1d500263536d0d67f20b345371efadd4d3781', '2026-01-02 14:26:09', '2026-01-02 13:56:45', '2026-01-02 21:56:09'),
(40, 140, 'b9a771c096740399f803648394649358b917e1efb49efa7569c5b78ade135c1c', '2026-01-03 02:17:24', '2026-01-03 01:47:55', '2026-01-03 09:47:24'),
(41, 141, '14b42ed1fb9be7fa5b114f23844c1969d51e6f1ce5062154a040ac40479d6b39', '2026-01-03 11:23:50', NULL, '2026-01-03 18:53:50'),
(42, 142, '844c85fc5b4f6f2dd7fb9991e5c21d19871b3846c135c278a0225bfc30b9872c', '2026-01-03 11:40:34', '2026-01-03 11:11:38', '2026-01-03 19:10:34'),
(43, 143, 'ffdb523d41d02628436983c8cff764451529d7ea5973e8abd93a7338cde7e02c', '2026-01-03 11:54:19', '2026-01-03 11:25:06', '2026-01-03 19:24:19'),
(44, 144, 'b3832408146a8238335ae47c4a0e2366c4dd36c16614e9c3bd72f9ebdc0276bc', '2026-01-04 10:51:32', '2026-01-04 10:21:53', '2026-01-04 18:21:32'),
(46, 146, '1fa69e743c1a0390f60aa5cbdd045a965df4a44f66480854389e53e8c5c4ce25', '2026-07-04 06:49:58', '2026-07-04 06:20:19', '2026-07-04 12:19:58'),
(47, 147, '1c8dffa6688389059311856911bb2c675a3611a737b6a2c18e499794261982a3', '2026-07-06 09:27:38', '2026-07-06 08:57:59', '2026-07-06 14:57:38');

-- --------------------------------------------------------

--
-- Table structure for table `employment`
--

CREATE TABLE `employment` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `employment_status` enum('employed','unemployed','self_employed','studying') DEFAULT NULL,
  `mobility` enum('local','international') DEFAULT NULL,
  `work_arrangement` enum('On-site','Remote','Hybrid') DEFAULT NULL,
  `company_country` varchar(100) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `it_category` enum('IT','NON_IT') DEFAULT NULL,
  `salary_per_month` decimal(10,2) DEFAULT NULL,
  `year_of_employment` int(11) DEFAULT NULL,
  `company_type` enum('private','public','ngo_ingo','self_employed','government') DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `company_address` varchar(255) DEFAULT NULL,
  `start_date` varchar(255) DEFAULT NULL,
  `end_date` varchar(255) DEFAULT NULL,
  `job_status` enum('Permanent','Temporary','Contractual','Job Order/Casual','Self Employed') DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `company_province` varchar(100) DEFAULT NULL,
  `company_city` varchar(100) DEFAULT NULL,
  `company_barangay` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employment`
--

INSERT INTO `employment` (`id`, `user_id`, `job_title`, `employment_status`, `mobility`, `work_arrangement`, `company_country`, `company_name`, `industry`, `it_category`, `salary_per_month`, `year_of_employment`, `company_type`, `position`, `company_address`, `start_date`, `end_date`, `job_status`, `company`, `company_province`, `company_city`, `company_barangay`, `created_at`, `updated_at`) VALUES
(34, 113, 'IT SERVICE MANAGEMENT (SERVICENOW, ITSM) CONSULTANT', 'employed', 'international', 'On-site', 'ALGERIA', 'HCL TECHNOLOGIES', 'GOVERNMENT', 'IT', 60000.00, 2009, 'private', NULL, NULL, NULL, NULL, 'Temporary', NULL, 'UTTAR PRADESH', 'NOIDA', NULL, '2025-12-15 18:09:31', NULL),
(36, 114, 'IT OPERATION', 'employed', 'international', 'Remote', 'CANADA', 'CONCENTRIX INTERNATIONAL', 'BIOTECHNOLOGY', 'IT', 50000.00, 2020, 'private', NULL, '0218 BLK 28', '2021-07-16', '', 'Permanent', 'CONCENTRIX INTERNATIONAL', 'SHRINKEL', 'REPLEO', '', '2025-12-16 09:39:46', '2025-12-16 09:41:26'),
(37, 115, 'IT CONSULTANT', 'employed', 'local', 'Remote', 'PHILIPPINES', 'ABC TECH', 'TELECOMMUNICATIONS', 'IT', 0.00, 2021, 'private', NULL, 'MABINI', '2021-02-10', '', 'Permanent', 'ABC TECH', '043400000', '043428000', '043428020', '2025-12-16 13:51:28', '2025-12-16 13:53:22'),
(38, 115, 'IT OFFICER', 'employed', 'international', 'Remote', 'UNITED STATES', 'VA HUB', 'HEALTHCARE', 'NON_IT', NULL, 2022, 'private', NULL, 'MONTREAL', '20222-05-18', '', 'Temporary', 'VA HUB', 'WASHINGTON', 'DC', '', '2025-12-16 13:54:38', NULL),
(39, 116, 'ASSISTANT MANAGER', 'employed', 'local', 'On-site', 'PHILIPPINES', 'BDO UNIBANK', 'BANKING', 'NON_IT', 45000.00, 2021, 'public', NULL, NULL, NULL, NULL, 'Contractual', NULL, '037700000', '037708000', NULL, '2025-12-16 15:03:10', NULL),
(40, 117, 'CORPORATE IT', 'employed', 'local', 'Hybrid', 'PHILIPPINES', 'JSAS', 'BUSINESS SERVICES', 'IT', 0.00, 2025, 'private', NULL, NULL, NULL, NULL, 'Permanent', NULL, '041000000', '041014000', NULL, '2025-12-16 16:06:50', NULL),
(41, 117, 'ASSISTANT NURSE', 'employed', 'local', 'On-site', 'PHILIPPINES', 'NISSIN', 'HEALTHCARE', 'NON_IT', NULL, 2023, 'government', NULL, '9F MERIDIAN LIFE CENTER', '2024-01-17', '2025-01-17', 'Contractual', 'NISSIN', '041000000', '041003000', '041003017', '2025-12-16 16:08:34', '2025-12-16 16:17:29'),
(42, 118, '', 'unemployed', 'local', '', 'PHILIPPINES', '', '', NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '', '', NULL, '2025-12-28 04:44:03', NULL),
(44, 120, '', 'unemployed', 'local', '', 'PHILIPPINES', '', '', NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '', '', NULL, '2025-12-29 00:06:09', NULL),
(45, 121, '', 'unemployed', 'local', 'On-site', 'PHILIPPINES', '', '', NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, 'Temporary', NULL, '', '', NULL, '2025-12-30 13:01:10', NULL),
(46, 122, '', 'unemployed', 'local', '', 'PHILIPPINES', '', '', NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '', '', NULL, '2025-12-30 13:29:27', NULL),
(47, 124, '', 'unemployed', 'local', '', 'PHILIPPINES', '', '', NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '', '', NULL, '2025-12-31 06:10:47', NULL),
(48, 125, '', 'unemployed', 'local', '', 'PHILIPPINES', '', '', NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '', '', NULL, '2026-01-01 14:55:34', NULL),
(49, 126, '', 'unemployed', 'local', '', 'PHILIPPINES', '', '', NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '', '', NULL, '2026-01-01 23:39:01', NULL),
(50, 127, '', 'unemployed', 'local', '', 'PHILIPPINES', '', '', NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '', '', NULL, '2026-01-01 23:56:30', NULL),
(51, 128, 'JUNIOR SYSTEMS ANALYST', 'employed', 'local', 'Remote', 'PHILIPPINES', 'CLOUDVISTA SOLUTIONS INC.', 'OTHER', 'IT', 0.00, 2021, 'private', NULL, NULL, NULL, NULL, 'Permanent', NULL, '045800000', '045801000', NULL, '2026-01-02 08:28:30', NULL),
(52, 130, 'MARKETING ASSISTANT', 'employed', 'local', 'On-site', 'PHILIPPINES', 'LAGUNA PRIME RETAIL CORPORATION', 'RETAIL', 'NON_IT', 0.00, 2022, 'private', NULL, NULL, NULL, NULL, 'Contractual', NULL, '043400000', '043405000', NULL, '2026-01-02 11:50:25', NULL),
(53, 129, 'MARKETING OFFICER', 'employed', 'local', 'On-site', 'PHILIPPINES', 'JRV TRADING & SUPPLIES', 'RETAIL', 'NON_IT', 0.00, 2019, 'private', NULL, NULL, NULL, NULL, 'Permanent', NULL, '043400000', '043425000', NULL, '2026-01-02 11:51:40', NULL),
(54, 131, 'SOFTWARE ENGINEER', 'employed', 'local', 'Hybrid', 'PHILIPPINES', 'CONCENTRIX', 'TELECOMMUNICATIONS', 'IT', 0.00, 2017, 'private', NULL, NULL, NULL, NULL, 'Permanent', NULL, '025700000', '025704000', NULL, '2026-01-02 12:04:36', NULL),
(55, 133, 'MALL PROMOTIONS', 'employed', 'local', 'On-site', 'PHILIPPINES', 'SM SUPERMALLS', 'SALES', 'NON_IT', 0.00, 2021, NULL, NULL, NULL, NULL, NULL, 'Permanent', NULL, '045600000', '045624000', NULL, '2026-01-02 12:19:38', NULL),
(56, 134, 'TEACHER', 'employed', 'local', 'Hybrid', 'PHILIPPINES', 'SAN JOSE INTEGRATED HIGH SCHOOL', 'EDUCATION', 'NON_IT', 0.00, 2020, 'public', NULL, NULL, NULL, NULL, 'Permanent', NULL, '043400000', '043424000', NULL, '2026-01-02 13:02:55', NULL),
(57, 135, 'COMPUTER SYSTEMS ENGINEER', 'employed', 'local', 'On-site', 'PHILIPPINES', 'SOUTH LUZON AUTOMATION SOLUTIONS CORP.', 'MANUFACTURING', 'NON_IT', 0.00, 2019, 'private', NULL, NULL, NULL, NULL, 'Permanent', NULL, '043400000', '043405000', NULL, '2026-01-02 13:28:00', NULL),
(58, 136, 'SENIOR RESERVATIONS AND GUEST RELATIONS OFFICER', 'employed', 'local', 'Remote', 'PHILIPPINES', 'LUZON TRAVEL & LEISURE SERVICES CO.', 'TOURISM', 'NON_IT', 0.00, 2019, 'private', NULL, NULL, NULL, NULL, 'Permanent', NULL, '045800000', '045812000', NULL, '2026-01-02 13:38:16', NULL),
(59, 137, 'SENIOR ACCOUNTING ASSOCIATESENIOR ACCOUNTING ASSOCIATE', 'employed', 'local', 'Hybrid', 'PHILIPPINES', 'SOUTH LUZON FINANCIAL ADVISORY SERVICES INC.', 'FINANCE', 'NON_IT', 0.00, 2018, 'private', NULL, NULL, NULL, NULL, 'Permanent', NULL, '043400000', '043411000', NULL, '2026-01-02 13:45:00', NULL),
(60, 138, 'HR OFFICER', 'employed', 'local', 'On-site', 'PHILIPPINES', 'QUEZON HUMAN RESOURCES CONSULTING GROUP', 'HUMAN RESOURCES', 'NON_IT', 0.00, 2017, 'private', NULL, NULL, NULL, NULL, 'Permanent', NULL, '045600000', '045624000', NULL, '2026-01-02 13:54:54', NULL),
(61, 139, 'MANAGEMENT CONSULTING', 'employed', 'local', 'On-site', 'PHILIPPINES', 'ACCENTURE INC.', 'HUMAN RESOURCES', 'NON_IT', 0.00, 2020, 'private', NULL, NULL, NULL, NULL, 'Permanent', NULL, '036900000', '036916000', NULL, '2026-01-02 14:02:14', NULL),
(62, 141, 'JUNIOR HIGH SCHOOL TEACHER', 'employed', 'local', 'On-site', 'PHILIPPINES', 'SAN PABLO CITY NATIONAL HIGH SCHOOL', 'EDUCATION', 'NON_IT', 0.00, 2019, 'public', NULL, NULL, NULL, NULL, 'Permanent', NULL, '043400000', '043424000', NULL, '2026-01-03 11:08:17', NULL),
(63, 142, 'FRONT OFFICE SUPERVISOR', 'employed', 'local', 'On-site', 'PHILIPPINES', 'QUEEN MARGARETTE HOTEL', 'HOSPITALITY', 'NON_IT', 0.00, 2020, 'private', NULL, NULL, NULL, NULL, 'Permanent', NULL, '045600000', '045624000', NULL, '2026-01-03 11:18:23', NULL),
(64, 143, 'JUNIOR SOFTWARE DEVELOPER', 'employed', 'local', 'Hybrid', 'PHILIPPINES', 'CLOUDSTAFF SOLUTIONS INC.', 'SOFTWARE', 'IT', 0.00, 2018, 'private', NULL, NULL, NULL, NULL, 'Permanent', NULL, '035400000', '035409000', NULL, '2026-01-03 11:35:19', NULL),
(65, 144, 'ACCOUNTANT', 'employed', 'local', 'On-site', 'PHILIPPINES', 'VILLARICA', 'BANKING', 'NON_IT', 60000.00, 2019, 'public', NULL, 'PLOT NO. 3A, SECTOR 126', '2020-02-12', '', 'Permanent', 'VILLARICA', '030800000', '030804000', '030804050', '2026-01-04 10:23:38', '2026-01-04 10:24:35'),
(66, 146, 'EMPLOYEEJGSDASW', 'employed', 'international', 'On-site', 'VIETNAM', 'BSA', 'ENVIRONMENTAL', 'IT', 12300.00, 2022, 'public', NULL, 'SAN PABLO STB', '2026-07-08', '2026-07-30', 'Temporary', 'BSA', 'GGDFDFGFD', 'GDFGDF', '', '2026-07-04 04:32:05', '2026-07-04 04:38:12'),
(67, 146, 'EMPLOYEEJGSDASW', 'employed', 'local', 'On-site', 'PHILIPPINES', 'ABS', 'CONSTRUCTION', 'IT', 123400.00, 2025, 'private', NULL, 'SAN PABLO STB', '2026-07-18', '2026-08-08', 'Temporary', 'ABS', '031400000', '031415000', '031415016', '2026-07-04 07:23:40', NULL),
(68, 147, 'EMPLOYEE', 'employed', 'international', 'On-site', 'AUSTRALIA', 'ABS', 'HEALTHCARE', 'IT', 33222.00, 2016, 'public', NULL, 'SAN PABLO STB', '2026-07-14', '2026-08-07', 'Job Order/Casual', 'ABS', 'TEST', 'TEST', '', '2026-07-06 07:00:15', '2026-07-07 13:08:36'),
(70, 147, 'EMPLOYEEJGSDASW', 'employed', 'local', 'On-site', 'PHILIPPINES', 'ABS', 'CONSTRUCTION', 'IT', 12000.00, 2009, 'private', NULL, 'SAN PABLO STB', '2026-07-30', '2026-08-08', 'Temporary', 'ABS', '140100000', '140118000', '140118018', '2026-07-06 08:08:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `success` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`log_id`, `user_id`, `login_time`, `ip_address`, `success`) VALUES
(1, 5, '2025-11-09 04:03:53', '::1', 1),
(2, 1, '2025-11-09 04:04:18', '::1', 1),
(3, 5, '2025-11-09 04:11:06', '::1', 1),
(4, 36, '2025-11-09 04:14:36', '::1', 1),
(5, 1, '2025-11-09 04:14:51', '::1', 1),
(6, 36, '2025-11-09 04:15:43', '::1', 1),
(7, 4, '2025-11-09 04:16:54', '::1', 1),
(8, 4, '2025-11-10 11:15:23', '::1', 1),
(9, 4, '2025-11-10 11:30:07', '::1', 1),
(10, 43, '2025-11-10 11:52:06', '::1', 0),
(11, 43, '2025-11-10 11:52:24', '::1', 1),
(12, 4, '2025-11-10 12:21:09', '::1', 1),
(13, 45, '2025-11-10 12:49:23', '::1', 1),
(14, 45, '2025-11-10 13:01:57', '::1', 1),
(15, 45, '2025-11-10 13:12:19', '::1', 1),
(16, 5, '2025-11-10 13:29:28', '::1', 1),
(17, 4, '2025-11-10 13:31:28', '::1', 1),
(18, 36, '2025-11-10 14:09:37', '::1', 1),
(19, 4, '2025-11-10 14:21:23', '::1', 1),
(20, 45, '2025-11-10 14:33:10', '::1', 0),
(21, 45, '2025-11-10 14:33:15', '::1', 0),
(22, 45, '2025-11-10 14:33:16', '::1', 0),
(23, 45, '2025-11-10 14:33:21', '::1', 0),
(24, 45, '2025-11-10 14:33:25', '::1', 0),
(25, 45, '2025-11-10 14:33:37', '::1', 1),
(26, 45, '2025-11-10 17:14:07', '::1', 1),
(27, 4, '2025-11-10 17:30:32', '::1', 1),
(28, 4, '2025-11-10 17:41:05', '::1', 1),
(29, 47, '2025-11-10 17:41:20', '::1', 0),
(30, 47, '2025-11-10 17:41:25', '::1', 0),
(31, 47, '2025-11-10 17:41:31', '::1', 1),
(32, 45, '2025-11-10 18:05:20', '::1', 1),
(33, 4, '2025-11-11 00:41:35', '::1', 1),
(34, 45, '2025-11-11 00:42:18', '::1', 1),
(35, 47, '2025-11-11 01:19:48', '::1', 1),
(36, 45, '2025-11-11 01:20:11', '::1', 1),
(37, 47, '2025-11-11 01:21:45', '::1', 1),
(38, 45, '2025-11-11 01:24:29', '::1', 1),
(39, 4, '2025-11-11 04:32:03', '::1', 1),
(40, 4, '2025-11-11 13:19:01', '::1', 1),
(41, 45, '2025-11-11 13:22:34', '::1', 1),
(42, 45, '2025-11-11 13:24:56', '::1', 1),
(43, 45, '2025-11-11 13:26:58', '::1', 1),
(44, 45, '2025-11-11 15:36:26', '::1', 1),
(45, 4, '2025-11-11 15:40:19', '::1', 1),
(46, 45, '2025-11-11 16:09:05', '::1', 1),
(47, 45, '2025-11-11 18:44:19', '::1', 1),
(48, 36, '2025-11-12 01:38:13', '::1', 1),
(49, 4, '2025-11-12 01:56:11', '::1', 1),
(50, 4, '2025-11-12 02:24:43', '::1', 1),
(51, 4, '2025-11-12 02:27:57', '::1', 1),
(52, 45, '2025-11-12 02:32:21', '::1', 0),
(53, 45, '2025-11-12 02:32:30', '::1', 1),
(54, 45, '2025-11-12 02:33:36', '::1', 1),
(55, 4, '2025-11-12 02:34:46', '::1', 1),
(56, 45, '2025-11-12 02:36:49', '::1', 0),
(57, 4, '2025-11-12 02:37:44', '::1', 1),
(58, 55, '2025-11-13 13:02:23', '::1', 1),
(59, 4, '2025-11-13 13:10:31', '::1', 1),
(60, 55, '2025-11-14 11:53:11', '::1', 1),
(61, 4, '2025-11-14 12:00:23', '::1', 1),
(62, 5, '2025-11-14 12:40:48', '::1', 1),
(63, 55, '2025-11-14 12:55:59', '::1', 1),
(64, 55, '2025-11-14 13:04:54', '::1', 1),
(65, 5, '2025-11-14 13:09:39', '::1', 1),
(66, 5, '2025-11-14 13:09:45', '::1', 1),
(67, 4, '2025-11-14 13:10:04', '::1', 1),
(68, 4, '2025-11-14 13:10:19', '::1', 1),
(69, 5, '2025-11-14 13:11:37', '::1', 1),
(70, 4, '2025-11-14 13:11:50', '::1', 1),
(71, 5, '2025-11-14 13:55:04', '::1', 1),
(72, 4, '2025-11-14 15:42:29', '::1', 1),
(73, 55, '2025-11-17 14:51:36', '::1', 1),
(74, 4, '2025-11-19 15:56:55', '::1', 1),
(75, 55, '2025-11-19 16:38:09', '::1', 1),
(76, 4, '2025-11-20 00:32:11', '::1', 1),
(77, 36, '2025-11-20 00:39:10', '::1', 1),
(78, 4, '2025-11-20 01:11:01', '::1', 1),
(79, 36, '2025-11-20 01:13:46', '::1', 1),
(80, 4, '2025-11-20 13:10:13', '::1', 1),
(81, 55, '2025-11-21 17:54:12', '::1', 1),
(82, 4, '2025-11-22 15:06:14', '::1', 1),
(83, 55, '2025-11-23 05:38:03', '::1', 1),
(84, 4, '2025-11-23 05:39:13', '::1', 1),
(85, 55, '2025-11-23 06:46:35', '::1', 1),
(86, 4, '2025-11-23 06:47:47', '::1', 1),
(87, 55, '2025-11-23 06:48:29', '::1', 1),
(88, 55, '2025-11-23 06:53:06', '::1', 0),
(89, 55, '2025-11-23 06:53:13', '::1', 1),
(90, 55, '2025-11-23 06:54:26', '::1', 1),
(91, 4, '2025-11-23 06:54:37', '::1', 1),
(92, 4, '2025-11-23 06:57:45', '::1', 1),
(93, 55, '2025-11-23 06:58:25', '::1', 0),
(94, 55, '2025-11-23 06:58:30', '::1', 1),
(95, 4, '2025-11-23 06:58:40', '::1', 1),
(96, 4, '2025-11-23 06:59:03', '::1', 1),
(97, 55, '2025-11-23 06:59:09', '::1', 1),
(98, 4, '2025-11-23 07:04:43', '::1', 1),
(99, 55, '2025-11-23 07:04:49', '::1', 1),
(100, 4, '2025-11-23 07:05:23', '::1', 1),
(101, 55, '2025-11-23 07:05:35', '::1', 1),
(102, 4, '2025-11-23 07:06:10', '::1', 1),
(103, 4, '2025-11-23 07:06:34', '::1', 1),
(104, 55, '2025-11-23 07:06:36', '::1', 1),
(105, 55, '2025-11-23 07:06:57', '::1', 1),
(106, 4, '2025-11-23 07:09:03', '::1', 1),
(107, 55, '2025-11-23 07:09:13', '::1', 1),
(108, 4, '2025-11-23 07:09:37', '::1', 1),
(109, 55, '2025-11-23 09:35:32', '::1', 1),
(110, 4, '2025-11-23 09:42:37', '::1', 1),
(111, 4, '2025-11-23 13:03:26', '::1', 1),
(112, 4, '2025-11-23 14:54:54', '::1', 1),
(113, 4, '2025-11-24 01:12:45', '::1', 1),
(114, 55, '2025-11-24 03:37:28', '::1', 1),
(115, 55, '2025-11-24 04:41:37', '::1', 1),
(116, 55, '2025-11-24 06:09:41', '::1', 1),
(117, 4, '2025-11-24 07:54:01', '::1', 1),
(118, 55, '2025-11-24 07:59:34', '::1', 1),
(119, 5, '2025-11-24 12:32:33', '::1', 1),
(120, 55, '2025-11-24 13:10:50', '::1', 1),
(121, 55, '2025-11-24 13:21:51', '::1', 1),
(122, 55, '2025-11-24 13:33:30', '::1', 1),
(123, 4, '2025-11-24 17:08:28', '::1', 1),
(124, 4, '2025-11-24 17:22:42', '::1', 1),
(125, 4, '2025-11-25 03:30:59', '::1', 1),
(126, 5, '2025-11-25 04:53:19', '::1', 1),
(127, 5, '2025-11-25 04:56:44', '::1', 1),
(128, 66, '2025-11-28 17:18:55', '::1', 0),
(129, 66, '2025-11-28 17:18:59', '::1', 0),
(130, 66, '2025-11-28 17:19:03', '::1', 0),
(131, 66, '2025-11-28 17:19:16', '::1', 0),
(132, 66, '2025-11-28 17:19:23', '::1', 0),
(133, 66, '2025-11-28 17:41:49', '::1', 0),
(134, 66, '2025-11-28 17:41:55', '::1', 1),
(135, 5, '2025-12-02 09:41:06', '136.158.65.154', 1),
(136, 4, '2025-12-02 09:43:42', '136.158.65.154', 1),
(137, 35, '2025-12-02 09:44:50', '136.158.65.154', 1),
(138, 5, '2025-12-02 09:45:07', '136.158.65.154', 1),
(139, 5, '2025-12-02 12:00:29', '136.158.65.154', 1),
(140, 5, '2025-12-02 12:10:39', '136.158.65.154', 1),
(141, 5, '2025-12-02 13:13:17', '136.158.64.157', 1),
(142, 35, '2025-12-02 13:40:04', '136.158.65.154', 1),
(143, 35, '2025-12-02 14:06:47', '136.158.65.154', 1),
(144, 67, '2025-12-02 15:04:11', '136.158.64.157', 1),
(145, 5, '2025-12-02 15:26:43', '136.158.64.157', 1),
(146, 5, '2025-12-02 15:37:55', '136.158.65.154', 1),
(147, 68, '2025-12-05 04:41:56', '136.158.65.61', 0),
(148, 35, '2025-12-05 04:43:20', '136.158.65.61', 0),
(149, 5, '2025-12-05 04:48:23', '136.158.65.61', 1),
(150, 5, '2025-12-05 05:47:07', '136.158.65.61', 1),
(151, 5, '2025-12-05 05:49:19', '136.158.65.61', 1),
(152, 5, '2025-12-05 05:57:13', '136.158.65.61', 1),
(153, 4, '2025-12-06 02:18:44', '103.66.223.88', 1),
(154, 4, '2025-12-06 04:16:29', '103.66.223.88', 1),
(155, 5, '2025-12-06 15:29:43', '136.158.64.105', 1),
(156, 4, '2025-12-06 15:40:20', '136.158.64.105', 1),
(157, 36, '2025-12-06 15:42:21', '136.158.64.105', 1),
(158, 4, '2025-12-06 15:42:40', '136.158.64.105', 1),
(159, 1, '2025-12-06 15:43:03', '136.158.64.105', 1),
(160, 35, '2025-12-07 12:20:01', '136.158.64.179', 1),
(161, 5, '2025-12-07 12:22:43', '136.158.64.179', 1),
(162, 35, '2025-12-07 12:28:22', '136.158.64.179', 1),
(163, 76, '2025-12-07 13:18:10', '103.66.223.88', 1),
(164, 4, '2025-12-07 14:52:21', '103.66.223.88', 1),
(165, 4, '2025-12-08 03:17:47', '103.66.223.88', 1),
(166, 4, '2025-12-08 03:42:58', '103.66.223.88', 1),
(167, 35, '2025-12-09 13:36:10', '136.158.64.79', 0),
(168, 35, '2025-12-09 13:36:19', '136.158.64.79', 1),
(169, 35, '2025-12-09 13:37:36', '136.158.64.79', 1),
(170, 5, '2025-12-09 13:42:01', '136.158.64.79', 1),
(171, 5, '2025-12-14 12:22:42', '136.158.64.14', 1),
(172, 4, '2025-12-14 12:29:59', '136.158.64.14', 1),
(173, 36, '2025-12-14 12:30:36', '136.158.64.14', 1),
(174, 5, '2025-12-14 12:31:53', '136.158.64.14', 1),
(175, 5, '2025-12-14 12:33:20', '136.158.64.14', 1),
(176, 78, '2025-12-14 12:34:41', '136.158.64.14', 1),
(177, 5, '2025-12-14 12:39:23', '136.158.64.14', 1),
(178, 5, '2025-12-14 12:58:29', '136.158.64.14', 1),
(179, 4, '2025-12-15 01:36:36', '103.66.223.88', 1),
(180, 5, '2025-12-15 02:49:36', '103.66.223.88', 1),
(181, 36, '2025-12-15 02:53:04', '103.66.223.88', 1),
(182, 5, '2025-12-15 03:02:30', '103.66.223.88', 1),
(183, 5, '2025-12-15 03:13:54', '103.66.223.88', 1),
(184, 5, '2025-12-15 04:03:22', '103.66.223.88', 1),
(185, 103, '2025-12-15 04:19:18', '103.66.223.88', 1),
(186, 109, '2025-12-15 04:52:27', '103.66.223.88', 1),
(187, 5, '2025-12-15 05:01:42', '103.66.223.88', 1),
(188, 5, '2025-12-15 14:50:39', '136.158.64.254', 1),
(189, 5, '2025-12-15 15:00:12', '103.72.191.146', 1),
(190, 110, '2025-12-15 15:08:45', '103.72.191.146', 0),
(191, 110, '2025-12-15 15:08:51', '103.72.191.146', 0),
(192, 110, '2025-12-15 15:08:59', '103.72.191.146', 0),
(193, 110, '2025-12-15 15:09:04', '103.72.191.146', 1),
(194, 4, '2025-12-15 15:42:02', '103.72.191.146', 1),
(195, 5, '2025-12-15 17:21:17', '103.66.223.88', 1),
(196, 109, '2025-12-15 17:23:14', '103.66.223.88', 1),
(197, 5, '2025-12-16 09:47:02', '136.158.64.14', 1),
(198, 4, '2025-12-16 09:51:49', '136.158.64.14', 1),
(199, 5, '2025-12-16 10:07:43', '136.158.64.14', 1),
(200, 114, '2025-12-16 10:12:14', '136.158.64.14', 1),
(201, 5, '2025-12-16 10:17:38', '136.158.64.14', 1),
(202, 5, '2025-12-16 10:20:38', '136.158.64.14', 1),
(203, 110, '2025-12-16 10:25:53', '136.158.64.14', 1),
(204, 4, '2025-12-16 10:45:07', '136.158.64.14', 1),
(205, 103, '2025-12-16 10:46:22', '136.158.64.14', 1),
(206, 5, '2025-12-16 10:52:23', '136.158.64.14', 1),
(207, 114, '2025-12-16 11:01:11', '136.158.64.14', 1),
(208, 5, '2025-12-16 13:57:35', '103.72.191.146', 1),
(209, 4, '2025-12-16 14:06:48', '112.198.200.44', 1),
(210, 114, '2025-12-16 14:32:56', '136.158.64.141', 1),
(211, 114, '2025-12-16 14:37:58', '136.158.64.141', 1),
(212, 115, '2025-12-16 14:44:20', '103.72.191.146', 1),
(213, 115, '2025-12-16 14:45:24', '103.72.191.146', 1),
(214, 115, '2025-12-16 14:45:54', '112.198.200.44', 1),
(215, 115, '2025-12-16 14:49:50', '136.158.64.141', 1),
(216, 114, '2025-12-16 14:52:36', '136.158.64.141', 1),
(217, 5, '2025-12-16 15:09:10', '136.158.64.141', 1),
(218, 5, '2025-12-16 15:09:48', '136.158.64.141', 1),
(219, 5, '2025-12-16 15:58:10', '136.158.64.95', 1),
(220, 117, '2025-12-16 16:16:27', '136.158.64.95', 1),
(221, 5, '2025-12-17 06:27:48', '136.158.64.254', 1),
(222, 113, '2025-12-18 01:37:23', '103.66.223.88', 1),
(223, 113, '2025-12-18 01:50:56', '103.66.223.88', 1),
(224, 113, '2025-12-18 01:58:58', '103.66.223.88', 1),
(225, 4, '2025-12-18 02:01:26', '103.66.223.88', 1),
(226, 103, '2025-12-18 02:10:11', '103.66.223.88', 1),
(227, 4, '2025-12-18 02:11:43', '103.66.223.88', 1),
(228, 113, '2025-12-18 02:38:37', '103.66.223.88', 1),
(229, 5, '2025-12-18 02:43:27', '136.158.64.254', 1),
(230, 116, '2025-12-18 02:57:28', '103.66.223.88', 1),
(231, 114, '2025-12-18 03:30:06', '136.158.64.254', 1),
(232, 5, '2025-12-18 03:31:23', '136.158.64.254', 1),
(233, 5, '2025-12-22 06:15:52', '136.158.64.179', 1),
(234, 118, '2025-12-28 04:44:40', '136.158.64.69', 1),
(235, 4, '2025-12-28 05:04:48', '136.158.65.155', 1),
(236, 4, '2025-12-28 05:09:24', '136.158.65.155', 1),
(237, 4, '2025-12-28 06:12:59', '136.158.65.155', 1),
(238, 4, '2025-12-28 06:13:55', '136.158.65.155', 1),
(239, 4, '2025-12-28 06:14:26', '136.158.65.155', 1),
(240, 4, '2025-12-28 06:22:06', '136.158.65.155', 1),
(241, 4, '2025-12-28 13:18:11', '103.66.223.88', 1),
(242, 114, '2025-12-28 13:29:13', '136.158.65.93', 1),
(243, 113, '2025-12-28 14:20:49', '103.66.223.88', 1),
(244, 103, '2025-12-28 14:39:33', '103.66.223.88', 1),
(245, 4, '2025-12-28 14:40:11', '103.66.223.88', 1),
(246, 4, '2025-12-29 10:03:38', '136.158.65.93', 1),
(247, 4, '2025-12-30 09:13:58', '150.228.183.10', 1),
(248, 4, '2025-12-30 10:24:07', '136.158.65.93', 1),
(249, 5, '2025-12-30 13:08:36', '103.72.191.146', 1),
(250, 5, '2025-12-31 11:55:25', '112.198.200.44', 1),
(251, 5, '2026-01-01 14:08:26', '103.72.191.146', 1),
(252, 125, '2026-01-01 14:53:55', '136.158.65.86', 1),
(253, 5, '2026-01-01 18:38:08', '136.158.64.21', 1),
(254, 5, '2026-01-02 06:48:12', '136.158.65.175', 1),
(255, 5, '2026-01-02 07:49:23', '136.158.65.175', 1),
(256, 4, '2026-01-02 07:51:42', '136.158.65.175', 1),
(257, 114, '2026-01-02 07:52:22', '136.158.65.175', 1),
(258, 5, '2026-01-02 08:25:17', '136.158.64.21', 1),
(259, 5, '2026-01-02 09:55:01', '103.72.191.146', 1),
(260, 5, '2026-01-02 11:43:52', '110.54.190.231', 1),
(261, 5, '2026-01-02 11:52:04', '112.198.200.44', 1),
(262, 5, '2026-01-02 11:52:51', '136.158.64.48', 1),
(263, 131, '2026-01-02 12:01:26', '136.158.64.48', 1),
(264, 4, '2026-01-02 12:05:31', '103.72.191.146', 1),
(265, 5, '2026-01-02 12:19:45', '103.72.191.146', 1),
(266, 5, '2026-01-02 12:50:13', '136.158.64.48', 1),
(267, 5, '2026-01-03 10:49:57', '112.198.200.44', 1),
(268, 141, '2026-01-03 11:03:25', '103.72.191.146', 1),
(269, 5, '2026-01-03 12:24:24', '136.158.65.115', 1),
(270, 4, '2026-01-03 13:35:16', '103.66.223.88', 1),
(271, 5, '2026-01-04 04:20:13', '110.54.190.110', 1),
(272, 5, '2026-01-04 09:54:10', '136.158.65.175', 1),
(273, 4, '2026-01-04 10:18:15', '136.158.65.175', 1),
(274, 103, '2026-01-04 10:19:19', '136.158.65.175', 1),
(275, 144, '2026-01-04 10:27:00', '136.158.65.175', 1),
(276, 5, '2026-01-04 11:15:44', '136.158.65.175', 1),
(277, 5, '2026-01-04 12:28:28', '136.158.65.175', 1),
(278, 5, '2026-01-04 13:20:38', '136.158.65.175', 1),
(279, 5, '2026-01-06 03:57:55', '2405:8d40:4482:b805:1887:fb73:ed60:2869', 1),
(280, 5, '2026-01-06 03:59:47', '203.177.99.202', 1),
(281, 144, '2026-01-06 05:33:48', '203.177.99.202', 0),
(282, 144, '2026-01-06 05:33:56', '203.177.99.202', 1),
(283, 5, '2026-01-06 14:06:37', '103.72.191.146', 1),
(284, 146, '2026-07-06 06:56:31', '::1', 0),
(285, 146, '2026-07-06 06:56:37', '::1', 0),
(286, 146, '2026-07-06 06:56:40', '::1', 0),
(287, 147, '2026-07-07 09:54:53', '::1', 0),
(288, 147, '2026-07-07 09:55:00', '::1', 0),
(289, 147, '2026-07-07 09:55:04', '::1', 1),
(290, 4, '2026-07-07 10:05:03', '::1', 1),
(291, 4, '2026-07-07 11:38:24', '::1', 1),
(292, 5, '2026-07-07 11:58:29', '::1', 1),
(293, 147, '2026-07-07 12:30:07', '::1', 1),
(294, 5, '2026-07-07 13:01:18', '::1', 1),
(295, 5, '2026-07-07 13:01:29', '::1', 1),
(296, 147, '2026-07-07 13:08:18', '::1', 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_verification_codes`
--

CREATE TABLE `login_verification_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal`
--

CREATE TABLE `personal` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `extension` varchar(50) DEFAULT NULL,
  `sex` enum('male','female') DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `institutional_email` varchar(255) DEFAULT NULL,
  `personal_email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `street_address` text DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `province` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `civil_status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personal`
--

INSERT INTO `personal` (`id`, `first_name`, `middle_name`, `last_name`, `extension`, `sex`, `dob`, `institutional_email`, `personal_email`, `phone_number`, `street_address`, `country`, `province`, `city`, `barangay`, `zip_code`, `profile_photo`, `user_id`, `facebook`, `civil_status`) VALUES
(44, 'Von', 'G', 'Malillos', 'N/A', 'male', '2025-12-16', 'vonmalillos@gmail.com', 'vonmalillos@gmail.com', '09143285706', '0296 PUROK 2', 'PHILIPPINES', '021500000', '021517000', '021517014', '4000', NULL, 113, NULL, 'SINGLE'),
(45, 'STEPHANIE ANGEL', 'CORNELIO', 'MAGDAME', 'N/A', 'female', '2004-09-09', 'angelcornelio0909@gmail.com', 'angelcornelio0909@gmail.com', '09454023150', '0293 STREET 2', 'PHILIPPINES', '043400000', '043424000', '043424051', '4000', 'uploads/profile_6941291173ccd_9c20fe9c75906e7b317903e68caaf906.jpeg', 114, NULL, 'SINGLE'),
(46, 'MARY GRACE', 'GUICO', 'ROSARIO', 'N/A', 'female', '2003-05-13', 'litygram8@gmail.com', 'litygram8@gmail.com', '09933796874', 'COTA', 'PHILIPPINES', '045600000', '045648000', '045648015', '4325', 'uploads/profile_6941661ced764_62b626da-7d28-4b4d-820d-51b36a7269df.jpg', 115, NULL, 'SINGLE'),
(47, 'JOHN CARLO', 'CABASE', 'BONCAJES', 'N/A', 'male', '2003-09-04', 'carloboncajes14@gmail.com', 'carloboncajes14@gmail.com', '09810511471', '0392 STREET 4', 'PHILIPPINES', '043400000', '043401000', '043401009', '3003', NULL, 116, NULL, 'MARRIED'),
(48, 'PRECIOUS', 'SELOZA', 'BRUEGAS', 'N/A', 'female', '2004-12-01', 'bruegasp@gmail.com', 'bruegasp@gmail.com', '09224571647', 'BLK 4 LOT 7 JAVE ST.', 'PHILIPPINES', '043400000', '043424000', '', '4000', 'uploads/profile_6941839a922d3_Screenshot 2025-10-25 013449.png', 117, NULL, 'SINGLE'),
(49, 'CHRISTIAN JAKE', '', 'DE CASTRO', 'N/A', 'male', '2004-03-08', 'decastrocj89@gmail.com', 'decastrocj89@gmail.com', '09815382672', 'PUROK 2', 'PHILIPPINES', '', 'SAN PABLO CITY', 'SAN ISIDRO', '4000', NULL, 118, NULL, 'SINGLE'),
(51, 'QUENNY MAE', '', 'FERNANDEZ', '-', 'female', '2003-10-17', 'fernandezq.2003@gmail.com', 'fernandezq.2003@gmail.com', '09954830956', 'PUROK 5', 'PHILIPPINES', '043400000', '', '', '4000', NULL, 120, NULL, 'SINGLE'),
(52, 'Erika', 'Gonzales', 'Enriquez', 'N/A', 'female', '2002-08-04', 'erikaenriquez080402@gmail.com', 'erikaenriquez080402@gmail.com', '09953872401', 'SITIO BULAKIN', 'PHILIPPINES', '045600000', '', '', '4325', NULL, 121, NULL, 'SINGLE'),
(53, 'CASEY', 'E.', 'TORIBIO', 'N/A', 'female', '2002-08-27', 'toribiocasey2@gmail.com', 'toribiocasey2@gmail.com', '09531376058', 'N/A', 'PHILIPPINES', '043400000', '043424000', '043424049', '4000', NULL, 122, NULL, 'SINGLE'),
(54, 'Jasmien', '', 'Redonario', NULL, NULL, NULL, 'redonariojasmien@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 123, NULL, NULL),
(55, 'ALLYSA MAE', 'FEDERIZO', 'PARO', 'N/A', 'female', '2004-04-02', 'allysamaeparo30@gmail.com', 'paroallysamae@gmail.com', '09776216601', 'SITIO SAGINGAN BRGY. SANTISIMO ROSARIO, SAN PABLO CITY, LAGUNA', 'PHILIPPINES', '043400000', '043424000', '043424075', '4000', NULL, 124, NULL, 'SINGLE'),
(56, 'KATHLYN', '', 'REYES', 'N/A', 'female', '2002-09-17', 'kathlynreyes0915@gmail.com', 'kathlynreyes0915@gmail.com', '0938 914 5162', '3RD', 'PHILIPPINES', '045600000', '045648000', '045648015', '4325', NULL, 125, NULL, 'SINGLE'),
(57, 'MIKA LUISE', '', 'ASISTIN', 'N/A', 'female', '2004-03-10', 'htmluise@gmail.com', 'mikmikluise@gmail.com', '09662665230', 'SAN PEDRO STREET', 'PHILIPPINES', '043400000', '043401000', '043401005', '4001', 'uploads/profile_69570595cb49c_inbound4972423715492689103.jpg', 126, NULL, 'SINGLE'),
(58, 'GENIE ANN', 'DE TORRES', 'SOMBESE', 'N/A', 'female', '2003-11-04', 'geniesombese@gmail.com', 'geniesombese@gmail.com', '09667740752', 'BRGY. SAN JOSE', 'PHILIPPINES', '043400000', '043424000', '043424053', '4000', 'uploads/profile_695709ae0a049_IMG_2955.jpeg', 127, NULL, 'SINGLE'),
(59, 'RIA', '', 'HAGARAP', 'N/A', 'female', '1998-08-15', 'paaatchiii@gmail.com', 'paaatchiii@gmail.com', '+63 917 684 2391', '128 MABINI STREET', 'PHILIPPINES', '043400000', '043424000', '043424013', '4000', NULL, 128, NULL, 'MARRIED'),
(60, 'JEROME ALLAN', 'CASTILLO', 'VILLAREAL', 'N/A', 'male', '1996-02-03', 'jscoleccion0@gmail.com', 'jscoleccion0@gmail.com', '09285542109', '41 SAMPAGUITA', 'PHILIPPINES', '043400000', '043425000', '043425011', '4023', NULL, 129, NULL, 'SINGLE'),
(61, 'JANINE', '', 'CELESTINO', 'N/A', 'female', '1999-11-03', 'kiyokosuzumi@gmail.com', 'kiyokosuzumi@gmail.com', '+63 905 372 8416', '47 RIZAL AVENUE', 'PHILIPPINES', '043400000', '043405000', '043405040', '4027', NULL, 130, NULL, 'SINGLE'),
(62, 'RESHELLE', 'BANAYO', 'BERNAL', 'N/A', 'female', '1995-10-15', 'bernalreshelle@gmail.com', 'bernalreshelle@gmail.com', '0942136582', 'STREET 3 0234', 'PHILIPPINES', '150700000', '150706000', '150706012', '4002', 'uploads/profile_6957b454a742b_IMG_3188.jpeg', 131, NULL, 'SINGLE'),
(63, 'Angela Marie', '', 'Cruz', NULL, NULL, NULL, 'grcrsr@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 132, NULL, NULL),
(64, 'DANIELLE RYE', '', 'MANALO', 'N/A', 'female', '1997-10-14', 'grcrsr133@gmail.com', 'grcrsr133@gmail.com', '09168834472', '123 NATIONAL HIGHWAY', 'PHILIPPINES', '045600000', '045641000', '045641014', '4324', NULL, 133, NULL, 'SINGLE'),
(65, 'JOYCE', '', 'EALA', 'N/A', 'female', '1997-02-27', 'dawnyvonne23@gmail.com', 'dawnyvonne23@gmail.com', '+63 927 518 6043', '9 SAMPAGUITA STREET', 'PHILIPPINES', '043400000', '043424000', '043424050', '4000', NULL, 134, NULL, 'MARRIED'),
(66, 'JOBET', 'LUIS', 'ARCEGA', 'N/A', 'male', '1996-06-10', 'toweltenfold@gmail.com', 'toweltenfold@gmail.com', '+63 918 403 7752', '21 QUEZON ROAD', 'PHILIPPINES', '045600000', '045648000', '', '4325', NULL, 135, NULL, 'MARRIED'),
(67, 'JOJIE', 'REPIA', 'VELINA', 'N/A', 'female', '1995-05-10', 'ilysmchifuyu@gmail.com', 'ilysmchifuyu@gmail.com', '+63 906 741 5829', '54 SAMPAGUITA EXTENSION', 'PHILIPPINES', '041000000', '041028000', '041028021', '4234', NULL, 136, NULL, 'MARRIED'),
(68, 'MARIA VELOS', '', 'SOLOMON', 'N/A', 'female', '1994-01-07', 'kimdoyochi01@gmail.com', 'kimdoyochi01@gmail.com', '+63 929 864 1750', '18 ILANG-ILANG STREET', 'PHILIPPINES', '043400000', '043411000', '043411001', '4030', NULL, 137, NULL, 'MARRIED'),
(69, 'VICTORIA', 'GIANAN', 'ORTIZ', 'N/A', 'female', '1993-04-19', 'nalakulets@gmail.com', 'nalakulets@gmail.com', '+63 995 482 7136', '77 MAHARLIKA HIGHWAY', 'PHILIPPINES', '045600000', '045624000', '045624020', '4301', NULL, 138, NULL, 'MARRIED'),
(70, 'AIRENE', 'MAGDAME', 'SALAMANCA', 'N/A', 'female', '2004-08-02', 'airenesalamanca@gmail.com', 'airenesalamanca@gmail.com', '09452356180', 'STREET 2 0236', 'PHILIPPINES', '043400000', '043424000', '', '4000', 'uploads/profile_6957cfe68efeb_inbound246956398056946940.jpg', 139, NULL, 'SINGLE'),
(71, 'Sherlyn Joyce', '', 'Hernandez', NULL, NULL, NULL, 'sherlynjoyceh@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 140, NULL, NULL),
(72, 'JOHN CARLO', '', 'MENDOZA', 'N/A', 'male', '1996-11-03', 'unknowncv382622@gmail.com', 'unknowncv382622@gmail.com', '09952037814', 'SITIO RIVERSIDE', 'PHILIPPINES', '043400000', '043424000', '043424050', '4000', NULL, 141, NULL, 'MARRIED'),
(73, 'KRISTINE ANNE', 'C.', 'VILLANUEVA', 'N/A', 'female', '1992-02-18', 'ociugyram@gmail.com', 'ociugyram@gmail.com', '091698244403', 'PHASE 2, BLOCK 4 LOT 6', 'PHILIPPINES', '043400000', '043401000', '043401010', '4001', NULL, 142, NULL, 'SINGLE'),
(74, 'JOSH DANIEL', 'AGUIRE', 'AQUINO', 'N/A', 'male', '1997-05-14', 'maryguico3@gmail.com', 'maryguico3@gmail.com', '09178325491', 'LOT 9 BLOCK 2', 'PHILIPPINES', '043400000', '043424000', '043424041', '4000', NULL, 143, NULL, 'SINGLE'),
(75, 'PANINAY', 'CORDEO', 'GUERRA', 'N/A', 'female', '1999-07-14', 'magdamestephanieangelcornelio@gmail.com', 'magdamestephanieangelcornelio@gmail.com', '09143285706', '1345 STREET 8', 'PHILIPPINES', '020900000', '020903000', '020903001', '40008', NULL, 144, NULL, 'MARRIED'),
(76, 'Test', 'T', 'Testewqewq', NULL, NULL, NULL, 'vonmalillos@mail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 145, NULL, NULL),
(77, 'Test', 'T', 'Bigoutsource', 'N/A', 'male', '2026-07-22', '0322-1829@lspu.edu.ph', 'vonmalillos@mail.com', '09635245956', 'SAN PABLO STB', 'MALAYSIA', 'JAKARTA', 'JAN LANG AKO', 'YEYE', '4234', NULL, 146, NULL, 'WIDOWED'),
(78, 'User1', 'User', 'Test', 'N/A', 'male', '1991-10-22', 'boombars123@gmail.com', 'vonmalillos@gmail.com', '09635245958', 'SAN PABLO STB', 'QATAR', 'TEST', 'TESTS', 'TEST', '4234', 'uploads/profile_6a4b60958a6f7_signature.png', 147, NULL, 'SINGLE');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `university_id` int(11) NOT NULL,
  `campus_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `level` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `university_id`, `campus_id`, `department_id`, `name`, `level`, `created_at`, `updated_at`) VALUES
(27, 2, 2, 13, 'Bachelor of Science in Information Technology', NULL, '2025-12-15 04:08:55', '2025-12-15 04:08:55'),
(28, 2, 2, 13, 'Bachelor of Science in Information Systems', NULL, '2025-12-15 04:09:11', '2025-12-15 04:10:59'),
(29, 2, 2, 14, 'Bachelor of Science in Entrepreneurship', NULL, '2025-12-15 04:10:12', '2025-12-15 04:10:12'),
(30, 2, 2, 11, 'Bachelor of Science in Accountancy', NULL, '2025-12-15 13:02:36', '2025-12-15 13:02:36'),
(31, 2, 2, 11, 'Bachelor of Science in Accounting Information System', NULL, '2025-12-15 13:02:59', '2025-12-15 13:02:59'),
(32, 2, 2, 11, 'Bachelor of Science in Management Accounting', NULL, '2025-12-15 13:03:14', '2025-12-15 13:03:14'),
(33, 2, 2, 18, 'Bachelor of Science in Nursing', NULL, '2025-12-15 13:03:46', '2025-12-15 13:03:46'),
(34, 2, 2, 12, 'Bachelor of Arts in Communication', NULL, '2025-12-15 13:04:07', '2025-12-15 13:04:07'),
(35, 2, 2, 12, 'Bachelor of Public Administration', NULL, '2025-12-15 13:04:19', '2025-12-15 13:04:19'),
(36, 2, 2, 12, 'Bachelor of Science in Psychology', NULL, '2025-12-15 13:04:31', '2025-12-15 13:04:31'),
(37, 2, 2, 12, 'Bachelor of Arts in Political Science', NULL, '2025-12-15 13:04:46', '2025-12-15 13:04:46'),
(38, 2, 2, 12, 'Bachelor of Science in Economics', NULL, '2025-12-15 13:04:59', '2025-12-15 13:04:59'),
(39, 2, 2, 14, 'Bachelor of Science in Business Administration', NULL, '2025-12-15 13:05:21', '2025-12-15 13:05:21'),
(40, 2, 2, 14, 'Bachelor of Science in Office Administration', NULL, '2025-12-15 13:05:33', '2025-12-15 13:05:33'),
(41, 2, 2, 15, 'Bachelor of Science in Computer Engineering', NULL, '2025-12-15 13:05:59', '2025-12-15 13:05:59'),
(42, 2, 2, 19, 'Bachelor of Early Childhood Education', NULL, '2025-12-15 13:06:23', '2025-12-15 13:06:23'),
(43, 2, 2, 19, 'Bachelor of Physical Education', NULL, '2025-12-15 13:06:34', '2025-12-15 13:06:34'),
(44, 2, 2, 19, 'Bachelor of Elementary Education', NULL, '2025-12-15 13:06:45', '2025-12-15 13:06:45'),
(46, 2, 2, 19, 'Bachelor of Secondary Education', NULL, '2025-12-15 13:07:15', '2025-12-15 13:07:15'),
(47, 2, 2, 19, 'Bachelor of Special Need Education', NULL, '2025-12-15 13:07:27', '2025-12-15 13:07:27'),
(48, 2, 2, 19, 'Bachelor of Technical-Vocational Teacher Education', NULL, '2025-12-15 13:07:42', '2025-12-15 13:07:42'),
(49, 2, 2, 19, 'Teacher Certificate Program', NULL, '2025-12-15 13:08:20', '2025-12-15 13:08:20'),
(50, 2, 2, 20, 'Bachelor of Science in Hospitality Management', NULL, '2025-12-15 13:08:42', '2025-12-15 13:08:42'),
(51, 2, 2, 20, 'Bachelor of Science in Tourism Management', NULL, '2025-12-15 13:08:55', '2025-12-15 13:08:55'),
(52, 3, 3, 21, 'Bachelor of Science in Information Technology', NULL, '2026-01-04 18:15:03', '2026-01-04 18:15:03');

-- --------------------------------------------------------

--
-- Table structure for table `specializations`
--

CREATE TABLE `specializations` (
  `id` int(11) NOT NULL,
  `university_id` int(11) NOT NULL,
  `campus_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `universities`
--

CREATE TABLE `universities` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `short_name` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `universities`
--

INSERT INTO `universities` (`id`, `name`, `short_name`, `created_at`, `updated_at`) VALUES
(2, 'Pamantasan ng Lungsod ng San Pablo', 'PLSP', '2025-11-24 21:45:00', '2025-12-02 13:14:21'),
(3, 'Laguna State Polytechnic University', 'LSPU', '2026-01-04 18:13:24', '2026-01-04 18:13:24');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('coordinator','admin','superadmin','alumni') NOT NULL DEFAULT 'alumni',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `onboarded` tinyint(1) DEFAULT 0,
  `college_id` int(11) DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `created_at`, `token_expires_at`, `onboarded`, `college_id`, `archived`) VALUES
(1, 'Alice Coordinator', 'alice.coord@univ.edu', '$2y$10$8SWyWwWVmzNb/I8.u71goeAOYnASgt4UAPnk0CnjgYBhCBDq9It5u', 'coordinator', '2025-07-24 09:22:37', NULL, 1, 1, 0),
(3, 'Carol Coordinator', 'carol.coord@univ.edu', 'hash3', 'coordinator', '2025-07-24 09:22:37', NULL, 1, 3, 0),
(4, 'Dave Admin', 'dave.admin@univ.edu', '$2y$10$8SWyWwWVmzNb/I8.u71goeAOYnASgt4UAPnk0CnjgYBhCBDq9It5u', 'admin', '2025-07-24 09:22:37', NULL, 1, NULL, 0),
(5, 'Eve Superadmin', 'eve.superadmin@univ.edu', '$2y$10$8SWyWwWVmzNb/I8.u71goeAOYnASgt4UAPnk0CnjgYBhCBDq9It5u', 'superadmin', '2025-07-24 09:22:37', NULL, 1, NULL, 0),
(100, 'College of Accountancy Coordinator', 'coord.college-of-accountancy@univ.edu', '$2y$10$7PZgBPXGXbRAwSiUc2Eji.vT19G1Tw2n16KonjmVfj6b/Jrn9jDY6', 'coordinator', '2025-12-15 04:12:46', NULL, 1, 20, 0),
(101, 'College of Arts and Sciences Coordinator', 'coord.college-of-arts-and-sciences@univ.edu', '$2y$10$7PZgBPXGXbRAwSiUc2Eji.vT19G1Tw2n16KonjmVfj6b/Jrn9jDY6', 'coordinator', '2025-12-15 04:12:46', NULL, 1, 21, 0),
(102, 'College of Business Administration Coordinator', 'coord.college-of-business-administration@univ.edu', '$2y$10$7PZgBPXGXbRAwSiUc2Eji.vT19G1Tw2n16KonjmVfj6b/Jrn9jDY6', 'coordinator', '2025-12-15 04:12:46', NULL, 1, 23, 0),
(103, 'College of Computer Studies and Technology Coordinator', 'coord.college-of-computer-studies-and-technology@univ.edu', '$2y$10$0vvvlirW/j6LGNqppJJKZ.Lv6x749thhdRGC9GfXILwz2Icglmjp.', 'coordinator', '2025-12-15 04:12:46', NULL, 1, 22, 0),
(104, 'College of Engineering Coordinator', 'coord.college-of-engineering@univ.edu', '$2y$10$7PZgBPXGXbRAwSiUc2Eji.vT19G1Tw2n16KonjmVfj6b/Jrn9jDY6', 'coordinator', '2025-12-15 04:12:46', NULL, 1, 24, 0),
(105, 'College of Human Kinetics Coordinator', 'coord.college-of-human-kinetics@univ.edu', '$2y$10$7PZgBPXGXbRAwSiUc2Eji.vT19G1Tw2n16KonjmVfj6b/Jrn9jDY6', 'coordinator', '2025-12-15 04:12:46', NULL, 1, 25, 0),
(106, 'College of Nursing and Allied Health Sciences Coordinator', 'coord.college-of-nursing-and-allied-health-sciences@univ.edu', '$2y$10$7PZgBPXGXbRAwSiUc2Eji.vT19G1Tw2n16KonjmVfj6b/Jrn9jDY6', 'coordinator', '2025-12-15 04:12:46', NULL, 1, 26, 0),
(107, 'College of Teacher Education Coordinator', 'coord.college-of-teacher-education@univ.edu', '$2y$10$7PZgBPXGXbRAwSiUc2Eji.vT19G1Tw2n16KonjmVfj6b/Jrn9jDY6', 'coordinator', '2025-12-15 04:12:46', NULL, 1, 27, 0),
(108, 'College of Tourism and Hospitality Management Coordinator', 'coord.college-of-tourism-and-hospitality-management@univ.edu', '$2y$10$7PZgBPXGXbRAwSiUc2Eji.vT19G1Tw2n16KonjmVfj6b/Jrn9jDY6', 'coordinator', '2025-12-15 04:12:46', NULL, 1, 28, 0),
(113, 'Von G Malillos', 'vonmalillos@gmail.com', '$2y$10$Hus607ZYF8elmBXA7ccZ9.Ry2m3mWwp9JdqUtLmqN57KDz7ncZqxO', 'alumni', '2025-12-15 10:08:03', NULL, 1, NULL, 0),
(114, 'Stephanie Angel Cornelio Magdame', 'angelcornelio0909@gmail.com', '$2y$10$5ys.t7KZzzxLSQGGmHrpf.Z7HNF4JysaOqNCNgO/vSRwMtLoGES5e', 'alumni', '2025-12-16 01:35:53', NULL, 1, NULL, 0),
(115, 'Mary Grace Guico Rosario', 'litygram8@gmail.com', '$2y$10$K8IIUFkJULnDP4ZiPLwHVegPIEyLbbuewKy9flAuUmRGcKDvs1fXK', 'alumni', '2025-12-16 05:44:50', NULL, 1, NULL, 0),
(116, 'John Carlo Cabase Boncajes', 'carloboncajes14@gmail.com', '$2y$10$9Qp3sVMQUOgodcV3AkB9CuonldaSbEPb3QSuWuFRIhR9khJJqpWv6', 'alumni', '2025-12-16 06:58:57', NULL, 1, NULL, 0),
(117, 'Precious Seloza Bruegas', 'bruegasp@gmail.com', '$2y$10$2.cnS6jORSHEhFPeNFoeneuE2tUHU7ODX75.wQv.NFw8hCpFEu1qW', 'alumni', '2025-12-16 08:01:30', NULL, 1, NULL, 0),
(118, 'Christian Jake  De Castro', 'decastrocj89@gmail.com', '$2y$10$BsMGRLklNcHHMMccx9y9Vepn//cR5nCdpWqMxX7wyOJ21hyme.GlG', 'alumni', '2025-12-27 20:40:11', NULL, 1, NULL, 0),
(120, 'Quenny Mae  Fernandez', 'fernandezq.2003@gmail.com', '$2y$10$mMOoJ2kdD6fycJOz0t5sNeccaKpFnA9cO1u4a2sCG8CfHKUuaJWOG', 'alumni', '2025-12-28 15:59:40', NULL, 1, NULL, 0),
(121, 'Erika Gonzales Enriquez', 'erikaenriquez080402@gmail.com', '$2y$10$Ojy7DB6ZTdkjqdU.npX6WOqpXaz.GBP7dtnE.Z2r7ZFdOC3uqATAi', 'alumni', '2025-12-30 04:55:37', NULL, 1, NULL, 0),
(122, 'Casey E. Toribio', 'toribiocasey2@gmail.com', '$2y$10$tKGq7.o2Svn2yYKY28Kl8Ot7lK6z7sk1TGelOXxjC/0oVF9Ia2eQW', 'alumni', '2025-12-30 05:25:24', NULL, 1, NULL, 0),
(123, 'Jasmien  Redonario', 'redonariojasmien@gmail.com', '$2y$10$iLwcMpaBzTgFOEiDj3BMVOI07Lunpj2ginXB9G5LNMfaiz8Y7gE8O', 'alumni', '2025-12-30 18:18:30', NULL, 0, NULL, 0),
(124, 'Allysa Mae Federizo Paro', 'allysamaeparo30@gmail.com', '$2y$10$KJzpsS4JOEvtmAM/6WM.e.Ap6uPcVtP2Se5Bj1r/G9uZmdtAt/aQa', 'alumni', '2025-12-30 22:06:46', NULL, 1, NULL, 0),
(125, 'Kathlyn  Reyes', 'kathlynreyes0915@gmail.com', '$2y$10$6t7LQe7Rlrs9zOqxHn8wF.XV/nQ9dmCvlCCKj46jEEv9IoNe/78h.', 'alumni', '2026-01-01 06:48:59', NULL, 1, NULL, 0),
(126, 'Mika Luise  Asistin', 'htmluise@gmail.com', '$2y$10$vSGTUjSFI4MVnCxf4.ADJOEW.aT.nDT8oHtOn5jRpb7RXM.4GyvLK', 'alumni', '2026-01-01 15:35:55', NULL, 1, NULL, 0),
(127, 'Genie Ann De Torres Sombese', 'geniesombese@gmail.com', '$2y$10$nzNGuhfOXePqa3m7/P2jGe2P2tZpwdkYGOgHQr1N7vZfbPna0Zr8.', 'alumni', '2026-01-01 15:53:28', NULL, 1, NULL, 0),
(128, 'Ria  Hagarap', 'paaatchiii@gmail.com', '$2y$10$SfX7gAWrx4hEC26tmlBuh.tIct9mNqz8c1oi28geliF77XIpuJhB6', 'alumni', '2026-01-02 00:02:42', NULL, 1, NULL, 0),
(129, 'Jerome Allan Castillo Villareal', 'jscoleccion0@gmail.com', '$2y$10$87mYAWWltLuT7e.rZdHe0uLVDQuPu7E53usJ/DL28W5L7xJkUhMXG', 'alumni', '2026-01-02 03:21:59', NULL, 1, NULL, 0),
(130, 'Janine  Celestino', 'kiyokosuzumi@gmail.com', '$2y$10$ysW04nzwqK91rbaQ4zuy/uruaUKHXN1AmkglLNuVd5UX6JG1nBFw6', 'alumni', '2026-01-02 03:45:56', NULL, 1, NULL, 0),
(131, 'Reshelle Banayo Bernal', 'bernalreshelle@gmail.com', '$2y$10$S2WrezQ1FnfXiB7JBIa1keUWRTaF1AfrxpJhvECPRC2ESUwCofbYO', 'alumni', '2026-01-02 03:55:06', NULL, 1, NULL, 0),
(132, 'Angela Marie  Cruz', 'grcrsr@gmail.com', '$2y$10$OTOXVGJ68I7EcXrtxKl0OuGRL2eBo1KYYkTxWGwDzdpKN8mkxpZHK', 'alumni', '2026-01-02 04:02:21', NULL, 0, NULL, 0),
(133, 'Danielle Rye  Manalo', 'grcrsr133@gmail.com', '$2y$10$VxiLAqzpcX0X9CXnZQttpu4jZj0P6T1NqqH4T5IA5WpIjASh3PdR.', 'alumni', '2026-01-02 04:08:47', NULL, 1, NULL, 0),
(134, 'Joyce  Eala', 'dawnyvonne23@gmail.com', '$2y$10$o9qGI.X.3n7OgTP02CM9X.EIMkIPZNPXtINxp1WovEZ194gXdtQFi', 'alumni', '2026-01-02 04:52:26', NULL, 1, NULL, 0),
(135, 'Jobet  Arcega', 'toweltenfold@gmail.com', '$2y$10$.k9p9.IuQMvRZiTLQDnjxeycglJgIhcutCrN0wsIdE/fabW8sguqK', 'alumni', '2026-01-02 05:08:51', NULL, 1, NULL, 0),
(136, 'Jojie Repia Velina', 'ilysmchifuyu@gmail.com', '$2y$10$qpYg2PJ4CAkGm1lhzsfcM./xwMNA0ykji7CsFLvUMCG60NU9/Lpnm', 'alumni', '2026-01-02 05:30:43', NULL, 1, NULL, 0),
(137, 'Maria Velos  Solomon', 'kimdoyochi01@gmail.com', '$2y$10$big3g8c.hATxqRW15LK/8eYxj8i0PK43thufzKHAN9pgAvTeUWqqy', 'alumni', '2026-01-02 05:40:46', NULL, 1, NULL, 0),
(138, 'Victoria Gianan Ortiz', 'nalakulets@gmail.com', '$2y$10$Jfi78mhLXN3d9kHOPEGscOULmDQuQuXjEW3/sQd5LUhRnE9V1ftqm', 'alumni', '2026-01-02 05:47:00', NULL, 1, NULL, 0),
(139, 'Airene Magdame Salamanca', 'airenesalamanca@gmail.com', '$2y$10$4YbvZ4mgjoh5QWhOD/PauOn8cE0tacMlqwhnYMeLznw9DjJ9X0/Oi', 'alumni', '2026-01-02 05:56:09', NULL, 1, NULL, 0),
(140, 'Sherlyn Joyce  Hernandez', 'sherlynjoyceh@gmail.com', '$2y$10$JFUqBOCUD2ZRISGCtIgS2ePB2BWcZKxR8EIWc6acy.9HKmi7pq.ke', 'alumni', '2026-01-02 17:47:24', NULL, 0, NULL, 0),
(141, 'John Carlo  Mendoza', 'unknowncv382622@gmail.com', '$2y$10$dlaDvbTW9M5oxduP7OPdouQr1kMzzTh477wF1De/CBMpZWAC7TKMG', 'alumni', '2026-01-03 02:53:50', NULL, 1, NULL, 0),
(142, 'Kristine Anne C. Villanueva', 'ociugyram@gmail.com', '$2y$10$rl8zoWyVAE/rdDT6Lj1Sre.IYIOUtaokScBkJcYSydZSJRMjlbKgu', 'alumni', '2026-01-03 03:10:34', NULL, 1, NULL, 0),
(143, 'Josh Daniel Aguire Aquino', 'maryguico3@gmail.com', '$2y$10$FvCoVAu2X6imsYQHhjbQSeWA29n1Q/D0qZ9bp9rpl.Mh0C.rl421u', 'alumni', '2026-01-03 03:24:19', NULL, 1, NULL, 0),
(144, 'Paninay Cordeo Guerra', 'magdamestephanieangelcornelio@gmail.com', '$2y$10$RFvcAH4t9dxYAQgn4giU8.TpdozkRyUEdawAey2cFg.BkLtz.1zhS', 'alumni', '2026-01-04 02:21:32', NULL, 1, NULL, 0),
(146, 'Test T Bigoutsource', '0322-1829@lspu.edu.ph', '$2y$10$1WAqTvoJ/F.ckIwRxrqSaOoX7fCzoRuRnsNbQHfzUh/ohZZN9hFK6', 'alumni', '2026-07-03 22:19:58', NULL, 1, NULL, 0),
(147, 'User1 User Test', 'boombars123@gmail.com', '$2y$10$FfkgQAYjhntt/MfiqO50i.8jzN2GwgmyheemoAdSMwD.a2X6AheQO', 'alumni', '2026-07-06 00:57:37', NULL, 1, NULL, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `awards`
--
ALTER TABLE `awards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `campuses`
--
ALTER TABLE `campuses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_campuses_university` (`university_id`);

--
-- Indexes for table `certifications`
--
ALTER TABLE `certifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `company_address`
--
ALTER TABLE `company_address`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_departments_university` (`university_id`),
  ADD KEY `fk_departments_campus` (`campus_id`);

--
-- Indexes for table `education`
--
ALTER TABLE `education`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_ver_user` (`user_id`),
  ADD KEY `idx_email_ver_hash` (`code_hash`);

--
-- Indexes for table `employment`
--
ALTER TABLE `employment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_job_status` (`job_status`),
  ADD KEY `idx_mobility` (`mobility`),
  ADD KEY `idx_work_arrangement` (`work_arrangement`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `login_verification_codes`
--
ALTER TABLE `login_verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_codes_user` (`user_id`),
  ADD KEY `idx_login_codes_hash` (`code_hash`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `personal`
--
ALTER TABLE `personal`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_programs_university` (`university_id`),
  ADD KEY `fk_programs_campus` (`campus_id`),
  ADD KEY `fk_programs_department` (`department_id`);

--
-- Indexes for table `specializations`
--
ALTER TABLE `specializations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_specializations_university` (`university_id`),
  ADD KEY `fk_specializations_campus` (`campus_id`),
  ADD KEY `fk_specializations_department` (`department_id`),
  ADD KEY `fk_specializations_program` (`program_id`);

--
-- Indexes for table `universities`
--
ALTER TABLE `universities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_college_id` (`college_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `campuses`
--
ALTER TABLE `campuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `certifications`
--
ALTER TABLE `certifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `company_address`
--
ALTER TABLE `company_address`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=270;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `education`
--
ALTER TABLE `education`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `employment`
--
ALTER TABLE `employment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=297;

--
-- AUTO_INCREMENT for table `login_verification_codes`
--
ALTER TABLE `login_verification_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `personal`
--
ALTER TABLE `personal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `specializations`
--
ALTER TABLE `specializations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `universities`
--
ALTER TABLE `universities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `campuses`
--
ALTER TABLE `campuses`
  ADD CONSTRAINT `fk_campuses_university` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_departments_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_departments_university` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  ADD CONSTRAINT `fk_email_ver_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `login_verification_codes`
--
ALTER TABLE `login_verification_codes`
  ADD CONSTRAINT `fk_login_codes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `fk_programs_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_programs_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_programs_university` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `specializations`
--
ALTER TABLE `specializations`
  ADD CONSTRAINT `fk_specializations_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_specializations_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_specializations_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_specializations_university` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
