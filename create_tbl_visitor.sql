CREATE TABLE IF NOT EXISTS `tbl_visitor` (
  `tbl_visitor_id` int(11) NOT NULL AUTO_INCREMENT,
  `visitor_name` varchar(255) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `contact_no` varchar(50) NOT NULL,
  `generated_code` varchar(255) NOT NULL,
  PRIMARY KEY (`tbl_visitor_id`),
  UNIQUE KEY `uq_visitor_generated_code` (`generated_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
