<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Storer;

use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Event\OrderAware;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class OrderStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly OrderProvider $orderProvider,
    ) {
    }

    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof OrderAware || isset($stored[OrderAware::ORDER_ID])) {
            return $stored;
        }

        $stored[OrderAware::ORDER_ID] = $event->getOrderId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(OrderAware::ORDER_ID)) {
            return;
        }

        $storable->setData(OrderAware::ORDER_ID, $storable->getStore(OrderAware::ORDER_ID));

        $storable->lazy(
            OrderAware::ORDER,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?OrderEntity
    {
        $id = $storableFlow->getStore(OrderAware::ORDER_ID);
        if ($id === null) {
            return null;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = $this->orderProvider->getCriteria($id, $storableFlow->getContext());

            $event = new BeforeLoadStorableFlowDataEvent(
                OrderDefinition::ENTITY_NAME,
                $criteria,
                $storableFlow->getContext(),
            );

            $this->dispatcher->dispatch($event, $event->getName());

            $order = $this->orderRepository->search($criteria, $storableFlow->getContext())->getEntities()->get($id);

            if ($order) {
                return $order;
            }

            return null;
        }

        return $this->orderProvider->getData($id, $storableFlow->getContext());
    }
}
