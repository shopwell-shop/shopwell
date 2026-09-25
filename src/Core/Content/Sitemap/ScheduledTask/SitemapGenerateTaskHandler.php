<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopwell\Core\Content\Sitemap\Service\SitemapSalesChannelLoader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[AsMessageHandler(handles: SitemapGenerateTask::class)]
final class SitemapGenerateTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly SitemapSalesChannelLoader $salesChannelLoader,
        private readonly SystemConfigService $systemConfigService,
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $sitemapRefreshStrategy = $this->systemConfigService->getInt('core.sitemap.sitemapRefreshStrategy');
        if ($sitemapRefreshStrategy !== SitemapExporterInterface::STRATEGY_SCHEDULED_TASK) {
            return;
        }

        $salesChannels = $this->salesChannelLoader->loadSalesChannels(Context::createCLIContext());

        foreach ($salesChannels as $salesChannel) {
            foreach ($this->salesChannelLoader->getLanguageIds($salesChannel) as $languageId) {
                $this->messageBus->dispatch(new SitemapMessage($salesChannel->getId(), $languageId, null, null, false));
            }
        }
    }
}
