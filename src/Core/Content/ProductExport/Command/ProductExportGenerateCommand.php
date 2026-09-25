<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Command;

use Shopwell\Core\Content\ProductExport\ProductExportEntity;
use Shopwell\Core\Content\ProductExport\ProductExportException;
use Shopwell\Core\Content\ProductExport\Service\ProductExporterInterface;
use Shopwell\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('inventory')]
#[AsCommand(
    name: 'product-export:generate',
    description: 'Generates a product export file',
)]
class ProductExportGenerateCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly ProductExporterInterface $productExportService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ignore cache and force generation')
            ->addOption('include-inactive', 'i', InputOption::VALUE_NONE, 'Include inactive exports')
            ->addArgument('sales-channel-id', InputArgument::REQUIRED, 'Sales channel ID of the corresponding Storefront sales channel to generate exports for')
            ->addArgument('product-export-id', InputArgument::OPTIONAL, 'Generate specific export ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $productExportId = $input->getArgument('product-export-id');
        $salesChannelId = $input->getArgument('sales-channel-id');
        $forceGeneration = $input->getOption('force');
        $includeInactive = $input->getOption('include-inactive');

        $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);

        if (!\in_array($salesChannelContext->getSalesChannel()->getTypeId(), ProductExportEntity::ALLOWED_SALES_CHANNEL_TYPE_IDS, true)) {
            throw ProductExportException::salesChannelNotAllowed();
        }

        $this->productExportService->export(
            $salesChannelContext,
            new ExportBehavior($forceGeneration, $includeInactive),
            $productExportId
        );

        return self::SUCCESS;
    }
}
