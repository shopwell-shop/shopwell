<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event\Command;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\BusinessEventCollector;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('framework')]
#[AsCommand(
    name: 'debug:business-events',
    description: 'Dumps all business events',
)]
class DebugDumpBusinessEventsCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(protected BusinessEventCollector $collector)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->collector->collect(Context::createCLIContext());

        $table = new Table($output);
        $table->setHeaders(['name', 'mail-aware', 'log-aware', 'class']);
        foreach ($result as $definition) {
            $table->addRow([
                $definition->getName(),
                (int) $definition->getAware('mailAware'),
                (int) $definition->getAware('logAware'),
                $definition->getClass(),
            ]);
        }
        $table->render();

        return self::SUCCESS;
    }
}
