CREATE TABLE IF NOT EXISTS `view` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `imge_id` int(11) NOT NULL,
  `seed` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`,`seed`,`imge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
