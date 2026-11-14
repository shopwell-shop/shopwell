<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Permission;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(name: 'workflow:roles:install', description: 'Create missing default workflow roles without changing existing roles or assigning users.')]
final class WorkflowDefaultRolesInstallCommand extends Command
{
    public function __construct(
        private readonly WorkflowDefaultRoleInstaller $installer,
        private readonly CoreMenuSynchronizer $menuSynchronizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->menuSynchronizer->synchronize();
        $created = $this->installer->install(Context::createCLIContext());
        (new SymfonyStyle($input, $output))->success(\sprintf('Created %d default workflow role(s). Existing roles were preserved; no users were assigned.', $created));

        return self::SUCCESS;
    }
}
