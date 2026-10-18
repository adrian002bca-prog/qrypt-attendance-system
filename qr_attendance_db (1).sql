-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 26, 2026 at 01:24 AM
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
-- Database: `qr_attendance_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_attendance`
--

CREATE TABLE `tbl_attendance` (
  `tbl_attendance_id` int(11) NOT NULL,
  `tbl_student_id` int(11) NOT NULL,
  `time_in` datetime NOT NULL DEFAULT current_timestamp(),
  `time_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tbl_attendance`
--

INSERT INTO `tbl_attendance` (`tbl_attendance_id`, `tbl_student_id`, `time_in`, `time_out`) VALUES
(1, 1, '2026-03-02 09:35:40', '2026-03-02 09:36:31'),
(2, 5, '2026-03-25 02:21:45', '2026-03-25 02:31:45'),
(3, 5, '2026-03-25 09:22:34', '2026-03-25 09:32:34');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_audit_log`
--

CREATE TABLE `tbl_audit_log` (
  `tbl_audit_log_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_audit_log`
--

INSERT INTO `tbl_audit_log` (`tbl_audit_log_id`, `username`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 'admin', 'CREATE', 'student', 2, 'Added student: MC Roco | 4-BSIS', '::1', '2026-03-02 09:16:51'),
(2, 'admin', 'AUTO_CLOSE', 'visitor_attendance', 1, 'Auto-closed unresolved IN record using cutoff 23:59', '::1', '2026-03-02 09:34:11'),
(3, 'admin', 'AUTO_CLOSE', 'visitor_attendance', 2, 'Auto-closed unresolved IN record using cutoff 23:59', '::1', '2026-03-02 09:34:11'),
(4, 'admin', 'AUTO_CLOSE', 'visitor_attendance', 3, 'Auto-closed unresolved IN record using cutoff 23:59', '::1', '2026-03-02 09:34:11'),
(5, 'admin', 'AUTO_CLOSE', 'visitor_attendance', 4, 'Auto-closed unresolved IN record using cutoff 23:59', '::1', '2026-03-02 09:34:11'),
(6, 'admin', 'AUTO_CLOSE', 'visitor_attendance', 5, 'Auto-closed unresolved IN record using cutoff 23:59', '::1', '2026-03-02 09:34:11'),
(7, 'admin', 'AUTO_CLOSE', 'visitor_attendance', 6, 'Auto-closed unresolved IN record using cutoff 23:59', '::1', '2026-03-02 09:34:11'),
(8, 'admin', 'AUTO_CLOSE', 'visitor_attendance', 7, 'Auto-closed unresolved IN record using cutoff 23:59', '::1', '2026-03-02 09:34:11'),
(9, 'admin', 'AUTO_CLOSE', 'visitor_attendance', 8, 'Auto-closed unresolved IN record using cutoff 23:59', '::1', '2026-03-02 09:34:11'),
(10, 'admin', 'AUTO_CLOSE', 'visitor_attendance', 9, 'Auto-closed unresolved IN record using cutoff 23:59', '::1', '2026-03-02 09:34:11'),
(11, 'admin', 'AUTO_CLOSE', 'visitor_attendance', 10, 'Auto-closed unresolved IN record using cutoff 23:59', '::1', '2026-03-02 09:34:11'),
(12, 'admin', 'TIME_IN', 'student_attendance', 1, 'Student timed in via scan', '::1', '2026-03-02 09:35:40'),
(13, 'admin', 'TIME_OUT', 'student_attendance', 1, 'Student timed out via scan', '::1', '2026-03-02 09:36:31'),
(14, 'admin', 'SCAN_BLOCKED', 'student', 1, 'Student scan blocked by cooldown (2s remaining)', '::1', '2026-03-02 09:36:39'),
(15, 'admin', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated auto-close cutoff to 23:59', '::1', '2026-03-02 09:37:17'),
(16, 'admin', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated auto-close cutoff to 23:59', '::1', '2026-03-02 09:37:21'),
(17, 'admin', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated scan sound to OFF', '::1', '2026-03-02 10:03:36'),
(18, 'admin', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated scan sound to ON', '::1', '2026-03-02 10:03:43'),
(19, 'admin', 'UPDATE', 'student', 1, 'Updated student: Efren Canja | BSIS 4 4', '::1', '2026-03-02 10:31:00'),
(20, 'admin', 'UPDATE', 'student', 1, 'Updated student: Efren Canja | BSIS 4', '::1', '2026-03-02 10:31:07'),
(21, 'admin', 'UPDATE', 'student', 2, 'Updated student: MC Roco | BSIS 4', '::1', '2026-03-02 10:31:21'),
(22, 'admin', 'CREATE', 'student', 3, 'Added student: Ariel Curiano | BSTM 4', '::1', '2026-03-02 10:45:27'),
(23, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSIS Year 4 Tuesday (15:40-18:40)', '::1', '2026-03-02 11:01:18'),
(24, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSTM Year 1 Monday (08:00-17:00)', '::1', '2026-03-02 11:13:30'),
(25, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSTM Year 1 Monday (08:00-17:00)', '::1', '2026-03-02 11:23:36'),
(26, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSTM Year 1 Monday (08:00-17:00)', '::1', '2026-03-02 11:23:39'),
(27, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSENTREP Year 4 Friday (08:00-13:00)', '::1', '2026-03-02 11:24:19'),
(28, 'admin', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated student time rules to start 15:00 and end 19:00', '::1', '2026-03-02 11:38:14'),
(29, 'admin', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated student time rules to start 15:00 and end 19:00', '::1', '2026-03-02 11:41:13'),
(30, 'admin', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated student time rules to start 15:00 and end 19:00', '::1', '2026-03-02 11:41:45'),
(31, 'admin', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated student time rules to start 15:00 and end 19:00', '::1', '2026-03-02 11:44:32'),
(32, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSENTREP Year 3 Monday (13:00-17:00)', '::1', '2026-03-02 11:45:34'),
(33, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSENTREP Year 3 Monday (13:00-17:00)', '::1', '2026-03-02 11:47:58'),
(34, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSENTREP Year 3 Monday (13:00-17:00)', '::1', '2026-03-02 11:48:02'),
(35, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSENTREP Year 3 Monday (13:00-17:00)', '::1', '2026-03-02 11:48:03'),
(36, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSENTREP Year 3 Monday (13:00-17:00)', '::1', '2026-03-02 11:48:04'),
(37, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSENTREP Year 3 Monday (13:00-17:00)', '::1', '2026-03-02 11:48:51'),
(38, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSENTREP Year 3 Monday (13:00-17:00)', '::1', '2026-03-02 11:48:58'),
(39, 'admin', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved student time rule: BSENTREP Year 3 Monday (13:00-17:00)', '::1', '2026-03-02 11:49:54'),
(40, 'admin', 'CREATE', 'student', 4, 'Added student: Jiko Suallo | BSENTREP 4', '::1', '2026-03-05 13:20:25'),
(41, 'unknown', 'BULK_UPSERT', 'student_time_rule', NULL, 'Bulk generated schedules for years 1-4, Mon-Fri using default 15:00-19:00; upserts=60', NULL, '2026-03-05 13:29:19'),
(42, 'unknown', 'BULK_UPSERT', 'student_time_rule', NULL, 'Replaced schedules with randomized times for years 1-4, Mon-Fri; upserts=60', NULL, '2026-03-05 13:31:18'),
(43, 'admin', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated student time rules: start 15:00, end 19:00, late grace 15m, early-out grace 15m', '::1', '2026-03-05 13:47:40'),
(44, 'admin', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated student time rules: start 15:00, end 19:00, late grace 15m, early-out grace 15m', '::1', '2026-03-05 13:49:35'),
(45, 'Qrypt', 'TIME_IN', 'student_attendance', 3, 'Demo student timed in (manual sample)', '::1', '2026-03-25 09:37:34'),
(46, 'Qrypt', 'TIME_OUT', 'student_attendance', 3, 'Demo student timed out (manual sample)', '::1', '2026-03-25 09:37:34'),
(47, 'Qrypt', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved schedule rule: BSENTREP Year 1 Monday (12:30-16:00) Room 101', '::1', '2026-03-25 09:50:18'),
(48, 'Qrypt', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved schedule rule: BSENTREP Year 1 Monday (13:30-16:00) Room Room 101', '::1', '2026-03-25 10:46:13'),
(49, 'Qrypt', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved schedule rule: BSIS Year 1 Monday (12:30-16:00) Room 101', '::1', '2026-03-25 10:47:11'),
(50, 'Qrypt', 'DELETE', 'student_time_rule', 54, 'Deleted schedule rule', '::1', '2026-03-25 10:55:56'),
(51, 'Qrypt', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved schedule rule: BSENTREP Year 1 Monday (12:30-16:00) Room 101', '::1', '2026-03-25 10:56:21'),
(52, 'Qrypt', 'UPDATE_SETTING', 'student_time_rule', NULL, 'Saved schedule rule: BSENTREP Year 1 Monday (13:00-16:00) Room 101', '::1', '2026-03-25 10:57:03'),
(53, 'Qrypt', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated student departments and year levels', '::1', '2026-03-25 11:23:50'),
(54, 'Qrypt', 'UPDATE_SETTING', 'system_setting', NULL, 'Updated student departments and year levels', '::1', '2026-03-25 11:26:12');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_scan_cooldown`
--

CREATE TABLE `tbl_scan_cooldown` (
  `tbl_scan_cooldown_id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `last_scanned_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_scan_cooldown`
--

INSERT INTO `tbl_scan_cooldown` (`tbl_scan_cooldown_id`, `entity_type`, `entity_id`, `last_scanned_at`) VALUES
(1, 'student', 1, '2026-03-02 09:36:31');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_student`
--

CREATE TABLE `tbl_student` (
  `tbl_student_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `year_level` tinyint(3) UNSIGNED DEFAULT NULL,
  `course_section` varchar(255) NOT NULL,
  `generated_code` varchar(255) NOT NULL,
  `photo_url` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tbl_student`
--

INSERT INTO `tbl_student` (`tbl_student_id`, `student_name`, `department`, `year_level`, `course_section`, `generated_code`, `photo_url`) VALUES
(1, 'Efren Canja', 'BSIS', 4, 'BSIS 4', 'Ri3a359QJTywfonD6J3gfsATaO9ljaMG', 'uploads/student_photos/student_20260302_083109_b925c1035e2221c6.png'),
(2, 'MC Roco', 'BSIS', 4, 'BSIS 4', 'MdA10Ieydy6m7EGLHWIUDoT6bx7I9zmV', NULL),
(3, 'Ariel Curiano', 'BSTM', 4, 'BSTM 4', 'iv5ZtCJJCau7h9WVdQidWivd1lKbUIBD', NULL),
(4, 'Jiko Suallo', 'BSENTREP', 4, 'BSENTREP 4', 'gBxZNMr4HJju0h66zLYOgBHkg45KMg2m', NULL),
(5, 'Demo Student', 'BSIS', 1, 'BSIS 1', 'DEMOSTD001', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_student_time_rule`
--

CREATE TABLE `tbl_student_time_rule` (
  `tbl_student_time_rule_id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `year_level` tinyint(3) UNSIGNED NOT NULL,
  `day_key` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `class_start_time` char(5) NOT NULL,
  `class_end_time` char(5) NOT NULL,
  `room` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_student_time_rule`
--

INSERT INTO `tbl_student_time_rule` (`tbl_student_time_rule_id`, `department`, `year_level`, `day_key`, `class_start_time`, `class_end_time`, `room`, `created_at`, `updated_at`) VALUES
(1, 'BSIS', 4, 2, '13:00', '16:30', 'Room 101', '2026-03-02 11:01:18', '2026-03-25 09:51:04'),
(2, 'BSTM', 1, 1, '10:00', '16:00', 'Room 201', '2026-03-02 11:13:30', '2026-03-25 09:51:04'),
(5, 'BSENTREP', 4, 5, '07:00', '12:00', 'Lab A', '2026-03-02 11:24:19', '2026-03-25 09:51:04'),
(6, 'BSENTREP', 3, 1, '11:00', '16:30', 'Lecture 1', '2026-03-02 11:45:34', '2026-03-25 09:51:04'),
(14, 'BSIS', 1, 1, '12:30', '16:00', '101', '2026-03-05 13:29:19', '2026-03-25 10:47:11'),
(15, 'BSIS', 1, 2, '12:30', '16:30', 'Lab C', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(16, 'BSIS', 1, 3, '10:00', '13:00', 'Lecture 1', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(17, 'BSIS', 1, 4, '11:30', '15:00', 'Lecture 2', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(18, 'BSIS', 1, 5, '09:00', '15:00', 'Room 101', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(19, 'BSIS', 2, 1, '12:30', '17:30', 'Room 102', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(20, 'BSIS', 2, 2, '13:30', '19:00', 'Room 103', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(21, 'BSIS', 2, 3, '07:00', '12:30', 'Room 104', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(22, 'BSIS', 2, 4, '09:30', '14:00', 'Room 201', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(23, 'BSIS', 2, 5, '10:00', '13:00', 'Room 202', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(24, 'BSIS', 3, 1, '13:30', '16:30', 'Room 203', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(25, 'BSIS', 3, 2, '08:30', '13:30', 'Lab A', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(26, 'BSIS', 3, 3, '12:30', '18:30', 'Lab B', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(27, 'BSIS', 3, 4, '12:30', '18:30', 'Lab C', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(28, 'BSIS', 3, 5, '10:30', '16:00', 'Lecture 1', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(29, 'BSIS', 4, 1, '11:00', '16:30', 'Lecture 2', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(31, 'BSIS', 4, 3, '13:00', '16:00', 'Room 102', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(32, 'BSIS', 4, 4, '12:30', '17:30', 'Room 103', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(33, 'BSIS', 4, 5, '08:30', '11:30', 'Room 104', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(35, 'BSTM', 1, 2, '12:00', '15:30', 'Room 202', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(36, 'BSTM', 1, 3, '11:30', '15:00', 'Room 203', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(37, 'BSTM', 1, 4, '13:30', '19:00', 'Lab A', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(38, 'BSTM', 1, 5, '09:00', '12:30', 'Lab B', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(39, 'BSTM', 2, 1, '07:30', '13:00', 'Lab C', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(40, 'BSTM', 2, 2, '12:30', '18:30', 'Lecture 1', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(41, 'BSTM', 2, 3, '08:30', '14:30', 'Lecture 2', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(42, 'BSTM', 2, 4, '10:30', '14:30', 'Room 101', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(43, 'BSTM', 2, 5, '09:00', '13:00', 'Room 102', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(44, 'BSTM', 3, 1, '13:30', '18:30', 'Room 103', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(45, 'BSTM', 3, 2, '08:00', '13:00', 'Room 104', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(46, 'BSTM', 3, 3, '13:00', '17:00', 'Room 201', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(47, 'BSTM', 3, 4, '09:30', '15:30', 'Room 202', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(48, 'BSTM', 3, 5, '09:30', '14:00', 'Room 203', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(49, 'BSTM', 4, 1, '08:00', '14:00', 'Lab A', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(50, 'BSTM', 4, 2, '13:30', '18:00', 'Lab B', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(51, 'BSTM', 4, 3, '12:30', '18:00', 'Lab C', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(52, 'BSTM', 4, 4, '08:00', '12:30', 'Lecture 1', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(53, 'BSTM', 4, 5, '10:00', '15:00', 'Lecture 2', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(55, 'BSENTREP', 1, 2, '07:00', '11:30', 'Room 102', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(56, 'BSENTREP', 1, 3, '12:00', '16:30', 'Room 103', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(57, 'BSENTREP', 1, 4, '13:30', '17:00', 'Room 104', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(58, 'BSENTREP', 1, 5, '08:00', '12:00', 'Room 201', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(59, 'BSENTREP', 2, 1, '12:00', '15:00', 'Room 202', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(60, 'BSENTREP', 2, 2, '12:30', '15:30', 'Room 203', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(61, 'BSENTREP', 2, 3, '08:30', '13:30', 'Lab A', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(62, 'BSENTREP', 2, 4, '11:00', '17:00', 'Lab B', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(63, 'BSENTREP', 2, 5, '08:30', '12:30', 'Lab C', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(65, 'BSENTREP', 3, 2, '09:30', '15:30', 'Lecture 2', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(66, 'BSENTREP', 3, 3, '11:00', '16:00', 'Room 101', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(67, 'BSENTREP', 3, 4, '11:30', '14:30', 'Room 102', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(68, 'BSENTREP', 3, 5, '12:30', '18:30', 'Room 103', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(69, 'BSENTREP', 4, 1, '12:30', '17:30', 'Room 104', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(70, 'BSENTREP', 4, 2, '10:00', '13:30', 'Room 201', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(71, 'BSENTREP', 4, 3, '12:00', '18:00', 'Room 202', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(72, 'BSENTREP', 4, 4, '08:30', '12:30', 'Room 203', '2026-03-05 13:29:19', '2026-03-25 09:51:04'),
(137, 'BSENTREP', 1, 1, '13:00', '16:00', '101', '2026-03-25 10:56:21', '2026-03-25 10:57:03');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_system_setting`
--

CREATE TABLE `tbl_system_setting` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_system_setting`
--

INSERT INTO `tbl_system_setting` (`setting_key`, `setting_value`, `updated_at`) VALUES
('auto_close_cutoff_time', '23:59', '2026-03-02 09:37:17'),
('auto_close_last_run_date', '2026-03-25', '2026-03-25 09:29:22'),
('scan_sound_enabled', '1', '2026-03-02 10:03:43'),
('student_class_end_time', '19:00', '2026-03-02 11:38:14'),
('student_class_start_time', '15:00', '2026-03-02 11:38:14'),
('student_departments', 'BSIS,BSENTREP,BSTM,ICT,HE,ABM', '2026-03-25 11:26:12'),
('student_early_out_grace_minutes', '15', '2026-03-05 13:47:40'),
('student_late_grace_minutes', '15', '2026-03-05 13:47:40'),
('student_year_levels', '11,12,1,2,3,4', '2026-03-25 11:26:12');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_visitor`
--

CREATE TABLE `tbl_visitor` (
  `tbl_visitor_id` int(11) NOT NULL,
  `visitor_name` varchar(255) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `contact_no` varchar(50) NOT NULL,
  `generated_code` varchar(255) NOT NULL,
  `qr_used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_visitor`
--

INSERT INTO `tbl_visitor` (`tbl_visitor_id`, `visitor_name`, `purpose`, `contact_no`, `generated_code`, `qr_used`) VALUES
(1, 'John Doe', 'Office', '09368542173', 'yUuEc2uTq0imUjkXYO7Pfx0qFeLFDSRu', 1),
(2, 'Visitor One', 'Document Submission', '09170000001', 'VIS_SAMPLE_01', 0),
(3, 'Visitor Two', 'Parent Meeting', '09170000002', 'VIS_SAMPLE_02', 0),
(4, 'Visitor Three', 'Campus Tour', '09170000003', 'VIS_SAMPLE_03', 0),
(5, 'Visitor Four', 'Delivery', '09170000004', 'VIS_SAMPLE_04', 0),
(6, 'Visitor Five', 'Interview', '09170000005', 'VIS_SAMPLE_05', 0),
(7, 'Visitor Six', 'Seminar', '09170000006', 'VIS_SAMPLE_06', 0),
(8, 'Visitor Seven', 'Enrollment Inquiry', '09170000007', 'VIS_SAMPLE_07', 0),
(9, 'Visitor Eight', 'IT Support', '09170000008', 'VIS_SAMPLE_08', 0),
(10, 'Visitor Nine', 'HR Concern', '09170000009', 'VIS_SAMPLE_09', 0),
(11, 'Visitor Ten', 'Alumni Visit', '09170000010', 'VIS_SAMPLE_10', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_visitor_attendance`
--

CREATE TABLE `tbl_visitor_attendance` (
  `tbl_visitor_attendance_id` int(11) NOT NULL,
  `tbl_visitor_id` int(11) NOT NULL,
  `time_in` datetime NOT NULL DEFAULT current_timestamp(),
  `time_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_visitor_attendance`
--

INSERT INTO `tbl_visitor_attendance` (`tbl_visitor_attendance_id`, `tbl_visitor_id`, `time_in`, `time_out`) VALUES
(1, 2, '2026-02-13 07:25:52', '2026-02-13 23:59:00'),
(2, 3, '2026-02-13 07:25:52', '2026-02-13 23:59:00'),
(3, 4, '2026-02-13 07:25:52', '2026-02-13 23:59:00'),
(4, 5, '2026-02-13 07:25:52', '2026-02-13 23:59:00'),
(5, 6, '2026-02-13 07:25:52', '2026-02-13 23:59:00'),
(6, 7, '2026-02-13 07:25:52', '2026-02-13 23:59:00'),
(7, 8, '2026-02-13 07:25:52', '2026-02-13 23:59:00'),
(8, 9, '2026-02-13 07:25:52', '2026-02-13 23:59:00'),
(9, 10, '2026-02-13 07:25:52', '2026-02-13 23:59:00'),
(10, 11, '2026-02-13 07:25:52', '2026-02-13 23:59:00'),
(11, 1, '2026-02-18 01:40:16', '2026-02-18 01:40:30');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_visitor_destination`
--

CREATE TABLE `tbl_visitor_destination` (
  `tbl_visitor_destination_id` int(11) NOT NULL,
  `tbl_visitor_attendance_id` int(11) NOT NULL,
  `tbl_visitor_id` int(11) NOT NULL,
  `destination_name` varchar(255) NOT NULL,
  `recorded_by` varchar(100) DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_attendance`
--
ALTER TABLE `tbl_attendance`
  ADD PRIMARY KEY (`tbl_attendance_id`),
  ADD KEY `fk_attendance_student_id` (`tbl_student_id`);

--
-- Indexes for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  ADD PRIMARY KEY (`tbl_audit_log_id`),
  ADD KEY `idx_audit_created_at` (`created_at`);

--
-- Indexes for table `tbl_scan_cooldown`
--
ALTER TABLE `tbl_scan_cooldown`
  ADD PRIMARY KEY (`tbl_scan_cooldown_id`),
  ADD UNIQUE KEY `uq_scan_cooldown_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `tbl_student`
--
ALTER TABLE `tbl_student`
  ADD PRIMARY KEY (`tbl_student_id`);

--
-- Indexes for table `tbl_student_time_rule`
--
ALTER TABLE `tbl_student_time_rule`
  ADD PRIMARY KEY (`tbl_student_time_rule_id`),
  ADD UNIQUE KEY `uq_student_time_rule_dept_year_day` (`department`,`year_level`,`day_key`);

--
-- Indexes for table `tbl_system_setting`
--
ALTER TABLE `tbl_system_setting`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `tbl_visitor`
--
ALTER TABLE `tbl_visitor`
  ADD PRIMARY KEY (`tbl_visitor_id`),
  ADD UNIQUE KEY `uq_visitor_generated_code` (`generated_code`);

--
-- Indexes for table `tbl_visitor_attendance`
--
ALTER TABLE `tbl_visitor_attendance`
  ADD PRIMARY KEY (`tbl_visitor_attendance_id`),
  ADD KEY `idx_visitor_attendance_visitor_id` (`tbl_visitor_id`);

--
-- Indexes for table `tbl_visitor_destination`
--
ALTER TABLE `tbl_visitor_destination`
  ADD PRIMARY KEY (`tbl_visitor_destination_id`),
  ADD KEY `idx_visitor_destination_attendance_id` (`tbl_visitor_attendance_id`),
  ADD KEY `idx_visitor_destination_visitor_id` (`tbl_visitor_id`),
  ADD KEY `idx_visitor_destination_recorded_at` (`recorded_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_attendance`
--
ALTER TABLE `tbl_attendance`
  MODIFY `tbl_attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  MODIFY `tbl_audit_log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `tbl_scan_cooldown`
--
ALTER TABLE `tbl_scan_cooldown`
  MODIFY `tbl_scan_cooldown_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_student`
--
ALTER TABLE `tbl_student`
  MODIFY `tbl_student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_student_time_rule`
--
ALTER TABLE `tbl_student_time_rule`
  MODIFY `tbl_student_time_rule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `tbl_visitor`
--
ALTER TABLE `tbl_visitor`
  MODIFY `tbl_visitor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tbl_visitor_attendance`
--
ALTER TABLE `tbl_visitor_attendance`
  MODIFY `tbl_visitor_attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tbl_visitor_destination`
--
ALTER TABLE `tbl_visitor_destination`
  MODIFY `tbl_visitor_destination_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_attendance`
--
ALTER TABLE `tbl_attendance`
  ADD CONSTRAINT `fk_attendance_student_id` FOREIGN KEY (`tbl_student_id`) REFERENCES `tbl_student` (`tbl_student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_visitor_attendance`
--
ALTER TABLE `tbl_visitor_attendance`
  ADD CONSTRAINT `fk_visitor_attendance_visitor_id` FOREIGN KEY (`tbl_visitor_id`) REFERENCES `tbl_visitor` (`tbl_visitor_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_visitor_destination`
--
ALTER TABLE `tbl_visitor_destination`
  ADD CONSTRAINT `fk_visitor_destination_attendance_id` FOREIGN KEY (`tbl_visitor_attendance_id`) REFERENCES `tbl_visitor_attendance` (`tbl_visitor_attendance_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_visitor_destination_visitor_id` FOREIGN KEY (`tbl_visitor_id`) REFERENCES `tbl_visitor` (`tbl_visitor_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
