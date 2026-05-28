<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

// Read DB settings from the env, with MariaDB defaults to preserve the
// historic single-stack behaviour. The docker-compose.pgsql.yml override
// flips these to pgsql/postgres without touching this file.
$dbtype = getenv('MOODLE_DOCKER_DBTYPE') ?: 'mariadb';
$dbhost = getenv('MOODLE_DOCKER_DBHOST') ?: 'mariadb';
$dbname = getenv('MOODLE_DOCKER_DBNAME') ?: 'moodle';
$dbuser = getenv('MOODLE_DOCKER_DBUSER') ?: 'moodle';
$dbpass = getenv('MOODLE_DOCKER_DBPASS') ?: 'moodle_password';

$CFG->dbtype    = $dbtype;
$CFG->dblibrary = 'native';
$CFG->dbhost    = $dbhost;
$CFG->dbname    = $dbname;
$CFG->dbuser    = $dbuser;
$CFG->dbpass    = $dbpass;
$CFG->prefix    = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => 0,
    'dbport' => $dbtype === 'pgsql' ? 5432 : 3306,
    'dbsocket' => '',
];
if ($dbtype !== 'pgsql') {
    $CFG->dboptions['dbcollation'] = 'utf8mb4_unicode_ci';
}

$CFG->wwwroot   = getenv('MOODLE_WWWROOT');
$CFG->sslproxy  = filter_var(getenv('MOODLE_SSLPROXY'), FILTER_VALIDATE_BOOLEAN);
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

// PHPUnit test configuration. Reuses the same engine/host/user/pass
// so PHPUnit hits whichever DB the container is wired up to.
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = '/var/www/moodledata_phpu';
$CFG->phpunit_dbtype    = $dbtype;
$CFG->phpunit_dblibrary = 'native';
$CFG->phpunit_dbhost    = $dbhost;
$CFG->phpunit_dbname    = 'moodle_test';
$CFG->phpunit_dbuser    = $dbuser;
$CFG->phpunit_dbpass    = $dbpass;

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
