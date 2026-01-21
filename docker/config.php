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

require_once(__DIR__ . '/lib/setup.php');
