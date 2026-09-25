<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Subscriber;

use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Random;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerBeforeDeleteSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly JsonEntityEncoder $jsonEntityEncoder
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityDeleteEvent::class => 'beforeDelete',
        ];
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        $context = $event->getContext();

        $ids = $event->getIds(CustomerDefinition::ENTITY_NAME);

        if ($ids === []) {
            return;
        }

        $source = $context->getSource();
        $salesChannelId = null;

        if ($source instanceof SalesChannelApiSource) {
            $salesChannelId = $source->getSalesChannelId();
        }

        $criteria = (new Criteria($ids))
            ->addAssociations([
                'salutation',
                'defaultBillingAddress.country',
                'defaultBillingAddress.countryState',
                'defaultBillingAddress.salutation',
                'defaultShippingAddress.country',
                'defaultShippingAddress.countryState',
                'defaultShippingAddress.salutation',
            ]);

        $customers = $this->customerRepository->search($criteria, $context)->getEntities();

        $salesChannelLanguages = $this->loadSalesChannelLanguages($customers, $salesChannelId, $context);

        $event->addSuccess(function () use ($customers, $context, $salesChannelId, $criteria, $salesChannelLanguages): void {
            foreach ($customers as $customer) {
                $languageId = $customer->getLanguageId();

                $effectiveSalesChannelId = $salesChannelId ?? $customer->getSalesChannelId();

                $effectiveLanguageId = $salesChannelLanguages
                    ->get($effectiveSalesChannelId)
                    ?->getLanguages()
                    ?->has($languageId)
                        ? $languageId
                        : null;

                $salesChannelContext = $this->salesChannelContextService->get(
                    new SalesChannelContextServiceParameters(
                        $effectiveSalesChannelId,
                        Random::getAlphanumericString(32),
                        $effectiveLanguageId,
                        null,
                        null,
                        $context,
                    )
                );

                $this->eventDispatcher->dispatch(new CustomerDeletedEvent(
                    $salesChannelContext,
                    $customer,
                    $this->jsonEntityEncoder->encode(
                        $criteria,
                        $this->customerRepository->getDefinition(),
                        $customer,
                        '/api/customer'
                    )
                ));
            }
        });
    }

    private function loadSalesChannelLanguages(CustomerCollection $customers, ?string $salesChannelIdFromSource, Context $context): SalesChannelCollection
    {
        $salesChannelIds = $salesChannelIdFromSource ? [$salesChannelIdFromSource] : $customers->getSalesChannelIds();

        $criteria = new Criteria($salesChannelIds);
        $association = $criteria->getAssociation('languages');

        $association
            ->addFields(['id'])
            ->addFilter(new EqualsAnyFilter('id', $customers->getLanguageIds()));

        return $this->salesChannelRepository->search($criteria, $context)->getEntities();
    }
}
