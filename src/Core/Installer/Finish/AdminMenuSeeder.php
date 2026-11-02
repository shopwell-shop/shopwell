<?php declare(strict_types=1);

namespace Shopwell\Core\Installer\Finish;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Writes the core navigation rows at the end of a web installation.
 *
 * The web installer never runs `system:install`, and the installer kernel carries no data
 * abstraction layer, so the rows are written by the same command a console installation
 * uses: one implementation of "what the core navigation looks like", two entry points.
 *
 * Best effort on purpose. An installation whose migrations ran, whose shop is configured
 * and whose administrator exists is complete even when the navigation still has to be
 * synchronized by hand afterwards, and a locked installation cannot be finished again -
 * so a failing console run must not turn the finish page into an error page.
 */
#[Package('framework')]
class AdminMenuSeeder
{
    public const COMMAND = 'admin-menu:sync';

    private const TIMEOUT_SECONDS = 300;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function seed(): void
    {
        $php = (new PhpExecutableFinder())->find(false);

        if ($php === false) {
            $this->reportFailure('no PHP binary found');

            return;
        }

        $process = new Process(
            [$php, $this->projectDir . '/bin/console', self::COMMAND, '--no-interaction'],
            $this->projectDir,
        );
        $process->setTimeout(self::TIMEOUT_SECONDS);

        try {
            $process->run();
        } catch (\Throwable $exception) {
            $this->reportFailure($exception->getMessage());

            return;
        }

        if (!$process->isSuccessful()) {
            $this->reportFailure($process->getErrorOutput() ?: $process->getOutput());
        }
    }

    /**
     * The installer has no logger: its container is built before the shop can read its own
     * configuration, so the failures end up in the error log the installation runs under.
     */
    private function reportFailure(string $reason): void
    {
        error_log(sprintf(
            'Could not write the core navigation rows (%s). Run "bin/console %s" once the installation is reachable.',
            trim($reason),
            self::COMMAND,
        ));
    }
}
