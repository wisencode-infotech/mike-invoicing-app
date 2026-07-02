-- Database + application user setup for the Invoicing App.
--
-- Run as the MySQL root user:
--   sudo mysql -u root -p < deploy/mysql/setup.sql
-- (or paste interactively into `sudo mysql -u root -p`)
--
-- Replace 'CHANGE_ME' with a real generated password before running —
-- do not use this literal value. Then put the same password in .env as
-- DB_PASSWORD.

CREATE DATABASE IF NOT EXISTS invoicing_app
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 'localhost' (not '%') — the app and MySQL run on the same box in this
-- single-VPS setup, so there's no reason for this user to be reachable
-- from anywhere else.
CREATE USER IF NOT EXISTS 'invoicing_app'@'localhost' IDENTIFIED BY 'CHANGE_ME';

GRANT ALL PRIVILEGES ON invoicing_app.* TO 'invoicing_app'@'localhost';

FLUSH PRIVILEGES;
