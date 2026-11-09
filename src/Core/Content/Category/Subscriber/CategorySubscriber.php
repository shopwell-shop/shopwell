<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Subscriber;

use Shopwell\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopwell\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class CategorySubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCategoryUrlGenerator $categoryUrlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.category.loaded' => 'salesChannelCategoryLoaded',
        ];
    }

    /**
     * @param SalesChannelEntityLoadedEvent<SalesChannelCategoryEntity> $event
     */
    public function salesChannelCategoryLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        $salesChannel = $event->getSalesChannelContext()->getSalesChannel();

        foreach ($event->getEntities() as $category) {
            $category->assign([
                'seoUrl' => $this->categoryUrlGenerator->generate($category, $salesChannel),
            ]);
        }
    }
}
