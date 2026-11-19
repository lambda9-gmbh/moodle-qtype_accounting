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

$options = getopt('', ['autoload:', 'env:', 'plugin:', 'reference:', 'context:', 'format:']);
require $options['autoload'];
if (isset($options['env'])) {
    (new \Symfony\Component\Dotenv\Dotenv())->usePutenv(true)->load($options['env']);
}
$root = realpath($options['plugin'] ?? getenv('PLUGIN_DIR'));
if ($root === false) {
    throw new RuntimeException('The complete plugin source directory is required.');
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
