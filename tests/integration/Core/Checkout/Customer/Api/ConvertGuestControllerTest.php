<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Api\ConvertGuestController;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopwell\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
class ConvertGuestControllerTest extends TestCase
{
    use EventDispatcherBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private ConvertGuestController $controller;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private readonly EntityRepository $customerRepository;

    protected function setUp(): void
    {
        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->controller = new ConvertGuestController(
            $this->customerRepository,
            $this->getContainer()->get(SalesChannelContextService::class),
            $this->getContainer()->get(ConvertGuestRoute::class),
            $this->getContainer()->get(SendPasswordRecoveryMailRoute::class),
            $this->getContainer()->get(Connection::class),
        );
    }

    public function testCannotConvertNotExisingCustomer(): void
    {
        $customerId = Uuid::randomHex();
        $request = new Request();

        static::expectException(CustomerException::class);

        $this->controller->convert($request, Context::createDefaultContext(), $customerId);
    }

    public function testCannotConvertARegisteredCustomer(): void
    {
        $customerId = $this->createCustomer();
        $request = new Request();

        static::expectException(CustomerException::class);

        $this->controller->convert($request, Context::createDefaultContext(), $customerId);
    }

    public function testCannotSendRecoveryWithoutDomainUrl(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request();

        $customerId = $this->createCustomer('test@test.com', guest: true);

        $customer = $this->customerRepository
            ->search(new Criteria([$customerId]), $context)->getEntities()
            ->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);

        $this->removeSalesChannelDomains($customer->getSalesChannelId(), $context);

        static::expectException(CustomerException::class);

        $this->controller->convert($request, $context, $customerId);
    }

    public function testConvertGuestWithPassword(): void
    {
        $request = new Request();
        $request->request->add(['password' => 'password']);

        $customerId = $this->createCustomer(guest: true);

        $caughtEvent = null;
        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            CustomerAccountRecoverRequestEvent::EVENT_NAME,
            function (CustomerAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->controller->convert($request, Context::createDefaultContext(), $customerId);

        $customer = $this->customerRepository->search(new Criteria([$customerId]), Context::createDefaultContext())->getEntities()->first();

        static::assertNull($caughtEvent);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertFalse($customer->getGuest());
        static::assertNotNull($customer->getPassword());
    }

    public function testConvertGuestWithoutPassword(): void
    {
        $request = new Request();
        $customerId = $this->createCustomer('test@test.com', guest: true);

        $caughtEvent = null;
        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            CustomerAccountRecoverRequestEvent::EVENT_NAME,
            function (CustomerAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->controller->convert($request, Context::createDefaultContext(), $customerId);

        $customer = $this->customerRepository->search(new Criteria([$customerId]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(CustomerAccountRecoverRequestEvent::class, $caughtEvent);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertFalse($customer->getGuest());
        static::assertNotNull($customer->getPassword());
    }

    private function removeSalesChannelDomains(string $salesChannelId, Context $context): void
    {
        $repository = $this->getContainer()->get('sales_channel_domain.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        $ids = $repository->searchIds($criteria, $context)->getPrimaryKeyData();
        if ($ids === []) {
            return;
        }

        $repository->delete($ids, $context);
    }
}
