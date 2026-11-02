<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Command;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Seeds or repairs the core navigation without waiting for the next administration
 * request. Deployments that inspect the table right after an install - or that want the
 * seeding to be part of the release rather than of the first login - run this explicitly.
 *
 * The command is a thin wrapper: the same synchronization runs on the first navigation
 * request and writes nothing when the table is already up to date.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
#[AsCommand(
    name: 'admin-menu:sync',
    description: 'Writes the core navigation declarations into the menu item table',
)]
class SyncAdminMenuCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly CoreMenuSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $written = $this->synchronizer->synchronize();

        if ($written === 0) {
            $output->writeln('The menu item table already matches the core declarations.');

            return self::SUCCESS;
        }

        $output->writeln(\sprintf('Wrote %d menu item row(s) from the core declarations.', $written));

        return self::SUCCESS;
    }
}
