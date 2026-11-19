<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Select and verify CI input files using Moodle Plugin CI's own filters.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$options = getopt('', ['autoload:', 'env:', 'plugin:', 'reference:', 'moodle:', 'context:', 'format:']);
require $options['autoload'];
if (isset($options['env'])) {
    (new \Symfony\Component\Dotenv\Dotenv())->usePutenv(true)->load($options['env']);
}
$root = realpath($options['plugin'] ?? getenv('PLUGIN_DIR'));
if ($root === false) {
    throw new RuntimeException('The complete plugin source directory is required.');
}

// File lists alone cannot prove parity: moodle-cs changes rules by Moodle
// version and treats an unknown version differently from Moodle 4.5.
if (isset($options['moodle']) || ($options['format'] ?? '') === 'context') {
    $moodleroot = isset($options['moodle']) ? realpath($options['moodle']) : false;
    if ($moodleroot === false) {
        throw new RuntimeException('A valid Moodle root is required for checker context verification.');
    }
    require_once dirname($options['autoload']) . '/squizlabs/php_codesniffer/autoload.php';
    defined('PHP_CODESNIFFER_VERBOSITY') || define('PHP_CODESNIFFER_VERBOSITY', 0);
    defined('PHP_CODESNIFFER_CBF') || define('PHP_CODESNIFFER_CBF', false);
    // Register PHPCS's token constants before constructing its ruleset.
    class_exists(\PHP_CodeSniffer\Util\Tokens::class);
    $config = new \PHP_CodeSniffer\Config(['--standard=moodle', '--parallel=1']);
    $ruleset = new \PHP_CodeSniffer\Ruleset($config);
    $file = new \PHP_CodeSniffer\Files\LocalFile($root . '/renderer.php', $ruleset, $config);
    $detectedroot = \MoodleHQ\MoodleCS\moodle\Util\MoodleUtil::getMoodleRoot($file, false);
    $branch = \MoodleHQ\MoodleCS\moodle\Util\MoodleUtil::getMoodleBranch($file, false);
    $versionfile = is_file($moodleroot . '/public/version.php')
        ? $moodleroot . '/public/version.php' : $moodleroot . '/version.php';
    if (!preg_match('/^\$branch\s*=\s*[\'"]([0-9]+)[\'"]\s*;/m', file_get_contents($versionfile), $matches)) {
        throw new RuntimeException('Cannot read the installed Moodle branch.');
    }
    $expectedbranch = (int) $matches[1];
    if ($detectedroot !== $moodleroot || $branch !== $expectedbranch) {
        throw new RuntimeException('PHPCS Moodle context differs from the installation: root=' .
            ($detectedroot ?? 'unknown') . ', branch=' . ($branch ?? 'unknown'));
    }
    printf("PHPCS context: Moodle %d, root %s\n", $branch, $detectedroot);
    if (($options['format'] ?? '') === 'context') {
        exit(0);
    }
}

$select = static function (string $directory, string $context): array {
    $plugin = new \MoodlePluginCI\Bridge\MoodlePlugin($directory);
    $plugin->context = $context;
    $pattern = $context === 'mustache' ? '*.mustache' : '*.php';
    $files = $plugin->getRelativeFiles(\Symfony\Component\Finder\Finder::create()->name($pattern));
    sort($files, SORT_STRING);
    return $files;
};

if (($options['format'] ?? 'verify') === 'csv') {
    $files = $select($root, $options['context']);
    echo implode(',', array_map(static fn ($file) => $root . '/' . $file, $files));
    exit(0);
}

// A pass must not depend on development files being absent from the container.
foreach (['dev/docker/config.php', 'dev/mobile/setup.php'] as $file) {
    if (!is_file($root . '/' . $file)) {
        throw new RuntimeException('Incomplete CI source tree: missing ' . $file);
    }
}

foreach (['phplint', 'phpcs', 'phpdoc', 'mustache'] as $context) {
    $files = $select($root, $context);
    foreach ($files as $file) {
        if (preg_match('~(^|/)(dev|vendor|node_modules)/~', $file)) {
            throw new RuntimeException($context . ' includes development/dependency file: ' . $file);
        }
    }
    $required = $context === 'mustache'
        ? ['templates/mobileapp/question.mustache']
        : ['renderer.php', 'classes/entries_table_builder.php', 'tests/edit_form_test.php', 'db/upgrade.php'];
    foreach ($required as $file) {
        if (!in_array($file, $files, true)) {
            throw new RuntimeException($context . ' excludes required plugin file: ' . $file);
        }
    }
    if (isset($options['reference'])) {
        $reference = $select(realpath($options['reference']), $context);
        if ($files !== $reference) {
            throw new RuntimeException($context . ': installed and checkout file lists differ.');
        }
    }
    printf("%s: %d files, sha256 %s\n", $context, count($files), hash('sha256', implode("\n", $files)));
}
