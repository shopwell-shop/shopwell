<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Shopwell\Core\Test\Integration\Traits\OrderFixture;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerMetaFieldSubscriberTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use OrderFixture;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    private Context $context;

    private StateMachineRegistry $stateMachineRegistry;

    protected function setUp(): void
    {
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->context = Context::createDefaultContext();
        $this->stateMachineRegistry = static::getContainer()->get(StateMachineRegistry::class);
    }

    public function testCompletingAndReopeningOrderUpdatesCustomerMetadata(): void
    {
        [$orderId, $customerId] = $this->createOrder();

        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_PROCESS);
        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_COMPLETE);

        $customer = $this->customerRepository->search(new Criteria([$customerId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(1, $customer->getOrderCount());
        static::assertSame(10, (int) $customer->getOrderTotalAmount());
        static::assertNotNull($customer->getLastOrderDate());

        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_REOPEN);

        $customer = $this->customerRepository->search(new Criteria([$customerId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(0, $customer->getOrderCount());
        static::assertSame(0, (int) $customer->getOrderTotalAmount());
        static::assertNull($customer->getLastOrderDate());
    }

    public function testDeletingCompletedOrderUpdatesCustomerMetadata(): void
    {
        [$orderId, $customerId] = $this->createOrder();
        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_PROCESS);
        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_COMPLETE);

        $customer = $this->customerRepository->search(new Criteria([$customerId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(1, $customer->getOrderCount());
        static::assertSame(10, (int) $customer->getOrderTotalAmount());
        static::assertNotNull($customer->getLastOrderDate());

        $this->orderRepository->delete([['id' => $orderId]], $this->context);

        $customer = $this->customerRepository->search(new Criteria([$customerId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(0, $customer->getOrderCount());
        static::assertSame(0, (int) $customer->getOrderTotalAmount());
        static::assertNull($customer->getLastOrderDate());
    }

    /**
     * @return array{string, string}
     */
    private function createOrder(): array
    {
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $this->context);
        $customerId = $orderData[0]['orderCustomer']['customer']['id'];
        static::assertIsString($customerId);

        $this->orderRepository->create($orderData, $this->context);

        return [$orderId, $customerId];
    }

    private function transitionOrder(string $orderId, string $action): void
    {
        $this->stateMachineRegistry->transition(
            new Transition('order', $orderId, $action, 'stateId'),
            $this->context,
        );
    }
}
