CREATE TABLE IF NOT EXISTS allowip (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    start INT UNSIGNED NOT NULL,
    end INT UNSIGNED NOT NULL,
    status TINYINT NOT NULL DEFAULT 0,
    comment TEXT DEFAULT NULL,
    INDEX allowip_range (status, start, end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO allowip (start, end, comment)
VALUES (2130706433, 2130706433, 'IPv4 loopback');
