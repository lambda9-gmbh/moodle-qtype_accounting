-- Create test database for PHPUnit tests
CREATE DATABASE IF NOT EXISTS moodle_test;
GRANT ALL PRIVILEGES ON moodle_test.* TO 'moodle'@'%';
FLUSH PRIVILEGES;
