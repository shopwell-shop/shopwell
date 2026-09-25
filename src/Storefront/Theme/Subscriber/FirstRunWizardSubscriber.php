<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\Subscriber;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Storefront\Theme\ThemeCollection;
use Shopwell\Storefront\Theme\ThemeLifecycleService;
use Shopwell\Storefront\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class FirstRunWizardSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<ThemeCollection> $themeRepository
     * @param EntityRepository<EntityCollection<Entity>> $themeSalesChannelRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly ThemeService $themeService,
        private readonly ThemeLifecycleService $themeLifecycleService,
        private readonly EntityRepository $themeRepository,
        private readonly EntityRepository $themeSalesChannelRepository,
        private readonly EntityRepository $salesChannelRepository
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FirstRunWizardFinishedEvent::class => 'frwFinished',
        ];
    }

    public function frwFinished(FirstRunWizardFinishedEvent $event): void
    {
        // only run on open -> completed|failed transition
        if (!$event->getPreviousState()->isOpen() || $event->getState()->isOpen()) {
            return;
        }

        $context = $event->getContext();

        $this->themeLifecycleService->refreshThemes($context);

        $themeCriteria = new Criteria();
        $themeCriteria->addAssociation('salesChannels');
        $themeCriteria->addFilter(new EqualsFilter('technicalName', 'Storefront'));

        $theme = $this->themeRepository->search($themeCriteria, $context)->getEntities()->first();
        if (!$theme) {
            throw new \RuntimeException('Default theme not found');
        }

        $themeSalesChannels = $theme->getSalesChannels();
        // only run if the themes are not already initialised
        if ($themeSalesChannels && $themeSalesChannels->count() > 0) {
            return;
        }

        $salesChannelCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $salesChannelIds = $this->salesChannelRepository->search($salesChannelCriteria, $context)->getEntities()->getIds();

        foreach ($salesChannelIds as $id) {
            $this->themeService->compileTheme($id, $theme->getId(), $context);
            $this->themeSalesChannelRepository->upsert([[
                'themeId' => $theme->getId(),
                'salesChannelId' => $id,
            ]], $context);
        }
    }
}
