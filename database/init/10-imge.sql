CREATE TABLE IF NOT EXISTS `imge` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket` varchar(255) NOT NULL,
  `seed` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `digest` varchar(255) NOT NULL,
  `upload_path` varchar(255) NOT NULL,
  `ext` varchar(255) NOT NULL,
  `upload_type` varchar(255) NOT NULL,
  `upload_size` int(11) NOT NULL,
  `mime_type` varchar(255) NOT NULL,
  `width` int(11) NOT NULL,
  `height` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `comment` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket` (`ticket`),
  UNIQUE KEY `digest` (`digest`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
