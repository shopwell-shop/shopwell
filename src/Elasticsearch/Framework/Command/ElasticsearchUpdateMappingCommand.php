<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Framework\Command;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('framework')]
#[AsCommand(
    name: 'es:mapping:update',
    description: 'Update the Elasticsearch indices mapping',
)]
class ElasticsearchUpdateMappingCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IndexMappingUpdater $indexMappingUpdater,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->indexMappingUpdater->update(Context::createCLIContext());

        return self::SUCCESS;
    }
}
