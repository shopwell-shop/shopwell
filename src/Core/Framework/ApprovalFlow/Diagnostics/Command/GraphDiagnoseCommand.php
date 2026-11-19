<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Diagnostics\Command;

use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(name: 'approval-flow:graph:diagnose', description: 'Checks draft graph structure, configuration and available capabilities without writing it')]
class GraphDiagnoseCommand extends Command
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly GraphDiagnostics $diagnostics,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Draft graph JSON file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $graph = new DraftGraph($this->filesystem->readFile($input->getArgument('file')));
        $report = $this->diagnostics->analyze($graph);
        $style = new SymfonyStyle($input, $output);
        if ($report->violations === []) {
            $style->success('Graph checks passed. This does not admit publication or execution.');

            return self::SUCCESS;
        }
        $rows = [];
        foreach ($report->violations as $violation) {
            $rows[] = [$violation->category->value, $violation->code, $violation->path, $violation->message];
        }
        $style->table(['Category', 'Code', 'Path', 'Message'], $rows);

        return self::FAILURE;
    }
}
