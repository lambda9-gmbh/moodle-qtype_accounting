-- Postgres init for the dev/CI stack.
-- Creates the PHPUnit test database alongside the primary Moodle DB.
-- Behat uses the primary DB with a different table prefix (bht_), so it
-- does not need a separate database.
CREATE DATABASE moodle_test;
GRANT ALL PRIVILEGES ON DATABASE moodle_test TO moodle;
