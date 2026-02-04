-- Create test database for Moodle 3.10 PHPUnit tests
CREATE DATABASE IF NOT EXISTS moodle310_test;
GRANT ALL PRIVILEGES ON moodle310_test.* TO 'moodle'@'%';
FLUSH PRIVILEGES;
