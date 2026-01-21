<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'mariadb';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'moodle';
$CFG->dbpass    = 'moodle_password';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array(
    'dbpersist' => 0,
    'dbport' => 3306,
    'dbsocket' => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
);

$CFG->wwwroot   = 'http://localhost:8080';
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

// PHPUnit test configuration.
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = '/var/www/moodledata_phpu';
$CFG->phpunit_dbtype    = 'mariadb';
$CFG->phpunit_dblibrary = 'native';
$CFG->phpunit_dbhost    = 'mariadb';
$CFG->phpunit_dbname    = 'moodle_test';
$CFG->phpunit_dbuser    = 'moodle';
$CFG->phpunit_dbpass    = 'moodle_password';

// Behat test configuration.
// Note: behat_wwwroot must be different from wwwroot and accessible from Selenium container.
$CFG->behat_wwwroot = 'http://moodle';
$CFG->behat_dataroot = '/var/www/behatdata';
$CFG->behat_prefix = 'bht_';
$CFG->behat_profiles = [
    'default' => [
        'browser' => 'firefox',
        'wd_host' => 'http://selenium:4444/wd/hub',
    ],
];
$CFG->behat_faildump_path = '/var/www/behatdata/faildump';

require_once(__DIR__ . '/lib/setup.php');
