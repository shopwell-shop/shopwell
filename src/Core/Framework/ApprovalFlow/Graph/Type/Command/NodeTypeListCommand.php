<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Command;

use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(name: 'approval-flow:node-types', description: 'Lists exact approval node declarations and their sources')]
class NodeTypeListCommand extends Command
{
    public function __construct(private readonly NodeTypeRegistry $registry)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];
        foreach ($this->registry->all() as $type) {
            $rows[] = [$type->type, $type->version, $type->configurationSchemaId, $type->source];
        }
        (new SymfonyStyle($input, $output))->table(['Type', 'Version', 'Configuration schema', 'Source'], $rows);

        return self::SUCCESS;
    }
}
