<?php
// Development-only integration regression. Run through ./dev/scripts/ci.sh scope.

use MoodlePluginCI\Bridge\MoodlePlugin;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

$options = getopt('', ['autoload:', 'command:', 'plugin:', 'moodle:']);
require $options['autoload'];
$source = $options['plugin'];
$temp = sys_get_temp_dir() . '/accounting-scope-test-' . bin2hex(random_bytes(8));
$installed = $temp . '-installed';
$envfile = $temp . '.env';
$filesystem = new Filesystem();
$filesystem->mkdir($temp);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $production = ['version.php', 'renderer.php', 'classes/entries_table_builder.php',
        'tests/edit_form_test.php', 'db/upgrade.php'];
    foreach (array_merge($production, ['.moodle-plugin-ci.yml', 'dev/docker/config.php', 'dev/mobile/setup.php',
        'templates/mobileapp/question.mustache']) as $file) {
        $filesystem->copy($source . '/' . $file, $temp . '/' . $file);
    }
    $filesystem->dumpFile($temp . '/vendor/dependency.php', '<?php broken dependency');
    $filesystem->dumpFile($temp . '/node_modules/dependency.php', '<?php broken dependency');
    foreach (['phplint', 'phpcs', 'phpdoc'] as $context) {
        $plugin = new MoodlePlugin($temp);
        $plugin->context = $context;
        $files = $plugin->getRelativeFiles(Finder::create()->name('*.php'));
        sort($files);
        $expected = $production;
        sort($expected);
        $expect($files === $expected, $context . ' must retain plugin code and tests, and exclude tools/dependencies.');
    }

    // A new untracked production file must still reach the actual PHP scanner.
    $filesystem->dumpFile($temp . '/untracked.php', '<?php function {');
    $lint = new Process([$options['command'], 'phplint', $temp]);
    $lint->run();
    $expect(!$lint->isSuccessful() && str_contains($lint->getOutput(), 'untracked.php'),
        'An invalid new production file must fail PHP lint.');
    $filesystem->remove([$temp . '/untracked.php', $temp . '/vendor', $temp . '/node_modules']);

    $check = new Process([$options['command'], 'phpcs', '--max-warnings', '0', $temp]);
    $check->run();
    $expect($check->isSuccessful(), 'The shared filter should pass PHPCS: ' . $check->getOutput());

    // A detached copy used to pass because moodle-cs treated its branch as
    // unknown. Exercise the actual version-sensitive sniff in both layouts.
    $coveragefixture = <<<'PHP'
<?php
namespace qtype_accounting;
/**
 * Coverage context probe, deliberately using only Moodle 5 coverage metadata.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_accounting_renderer::class)]
final class renderer_test extends \advanced_testcase {
    public function test_coverage_context(): void {
    }
}
PHP;
    $phpcs = dirname($options['autoload']) . '/bin/phpcs';
    $coverage = static function (string $file) use ($phpcs): array {
        $process = new Process([$phpcs, '--standard=moodle', '--sniffs=moodle.PHPUnit.TestCaseCovers',
            '--no-cache', '--report=json', $file]);
        $process->run();
        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    };
    foreach ([405, 501] as $branch) {
        $contextroot = $temp . '/context-' . $branch;
        $public = $contextroot . ($branch >= 501 ? '/public' : '');
        $fixture = $public . '/question/type/accounting/tests/renderer_test.php';
        $filesystem->dumpFile($contextroot . '/config-dist.php', '<?php');
        $filesystem->dumpFile($public . '/version.php', "<?php\n\$branch = '$branch';\n");
        $filesystem->dumpFile($fixture, $coveragefixture);
        $report = $coverage($fixture);
        $expect($report['totals']['errors'] === 0 && $report['totals']['warnings'] === ($branch < 500 ? 1 : 0),
            'The real coverage sniff must apply the detected Moodle ' . $branch . ' rules.');
        if ($branch < 500) {
            $message = array_values($report['files'])[0]['messages'][0];
            $expect($message['source'] === 'moodle.PHPUnit.TestCaseCovers.Missing',
                'The Moodle 4.5 fixture must reproduce the exact GitHub coverage warning.');
        }
    }
    $detached = $temp . '/tests/renderer_test.php';
    $filesystem->dumpFile($detached, $coveragefixture);
    $expect($coverage($detached)['totals']['warnings'] === 0,
        'Negative control: the detached copy must demonstrate the previous false pass.');
    $filesystem->remove([$temp . '/context-405', $temp . '/context-501', $detached]);

    $context = new Process([PHP_BINARY, $source . '/dev/scripts/ci-files.php',
        '--autoload=' . $options['autoload'], '--plugin=' . $source,
        '--moodle=' . $options['moodle'], '--format=context']);
    $context->run();
    $expect($context->isSuccessful(), 'The real scan workspace must retain Moodle context: ' .
        $context->getOutput() . $context->getErrorOutput());
    $missingcontext = new Process([PHP_BINARY, $source . '/dev/scripts/ci-files.php',
        '--autoload=' . $options['autoload'], '--plugin=' . $temp,
        '--moodle=' . $options['moodle'], '--format=context']);
    $missingcontext->run();
    $expect(!$missingcontext->isSuccessful() && str_contains($missingcontext->getOutput() .
        $missingcontext->getErrorOutput(), 'PHPCS Moodle context differs'),
        'A detached scan must fail the context guard before PHPCS can falsely pass.');

    // Exercise the exact GitHub guard, including loading the installer's .env.
    $filesystem->mirror($temp, $installed);
    $filesystem->dumpFile($envfile, 'PLUGIN_DIR="' . $installed . '"' . "\n");
    $guard = new Process([PHP_BINARY, $source . '/dev/scripts/ci-files.php',
        '--autoload=' . $options['autoload'], '--env=' . $envfile, '--reference=' . $temp, '--format=verify']);
    $guard->run();
    $expect($guard->isSuccessful(), 'The GitHub installed/checkout scope guard must pass: ' .
        $guard->getOutput() . $guard->getErrorOutput());
    $filesystem->dumpFile($installed . '/extra.php', '<?php // Additional installed source.');
    $guard->run();
    $expect(!$guard->isSuccessful() && str_contains($guard->getOutput() . $guard->getErrorOutput(), 'file lists differ'),
        'The GitHub guard must reject an installed/checkout file mismatch.');

    // Negative control: removing the filter must reproduce the reported GitHub errors.
    $filesystem->remove($temp . '/.moodle-plugin-ci.yml');
    $check->run();
    $output = $check->getOutput();
    $expect(!$check->isSuccessful(), 'PHPCS must fail when the shared filter is removed.');
    foreach (['dev/mobile/setup.php', 'dev/docker/config.php', 'MoodleInternalGlobalState'] as $text) {
        $expect(str_contains($output, $text), 'Expected GitHub failure was not reproduced: ' . $text);
    }
    echo "Scope regressions passed: file filters verified, Moodle 4.5/5.1 coverage rules reproduced, missing context rejected.\n";
} finally {
    $filesystem->remove([$temp, $installed, $envfile]);
}
