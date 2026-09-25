<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Storer;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\CustomerGroupProvider;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Event\CustomerGroupAware;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class CustomerGroupStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     */
    public function __construct(
        private readonly EntityRepository $customerGroupRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CustomerGroupProvider $customerGroupProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof CustomerGroupAware || isset($stored[CustomerGroupAware::CUSTOMER_GROUP_ID])) {
            return $stored;
        }

        $stored[CustomerGroupAware::CUSTOMER_GROUP_ID] = $event->getCustomerGroupId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(CustomerGroupAware::CUSTOMER_GROUP_ID)) {
            return;
        }

        $storable->setData(CustomerGroupAware::CUSTOMER_GROUP_ID, $storable->getStore(CustomerGroupAware::CUSTOMER_GROUP_ID));

        $storable->lazy(
            CustomerGroupAware::CUSTOMER_GROUP,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?CustomerGroupEntity
    {
        $id = $storableFlow->getStore(CustomerGroupAware::CUSTOMER_GROUP_ID);
        if ($id === null) {
            return null;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = $this->customerGroupProvider->getCriteria($id, $storableFlow->getContext());

            $event = new BeforeLoadStorableFlowDataEvent(
                CustomerGroupDefinition::ENTITY_NAME,
                $criteria,
                $storableFlow->getContext(),
            );

            $this->dispatcher->dispatch($event, $event->getName());

            $customerGroup = $this->customerGroupRepository->search($criteria, $storableFlow->getContext())->getEntities()->get($id);

            if ($customerGroup) {
                return $customerGroup;
            }

            return null;
        }

        return $this->customerGroupProvider->getData($id, $storableFlow->getContext());
    }
}
