<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\MessageQueue\Command;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('framework')]
#[AsCommand(
    name: 'scheduled-task:run-single',
    description: 'Allows to run one single scheduled task regardless of its schedule.',
)]
class RunSingleScheduledTaskCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly TaskRunner $taskRunner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('taskName', InputArgument::REQUIRED, 'Scheduled task name like log_entry.cleanup');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->taskRunner->runSingleTask($input->getArgument('taskName'), Context::createCLIContext());

        return self::SUCCESS;
    }
}
