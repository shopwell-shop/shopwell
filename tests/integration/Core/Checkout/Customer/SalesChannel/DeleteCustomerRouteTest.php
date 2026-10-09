<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\Test\Integration\Traits\CustomerTestTrait;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class DeleteCustomerRouteTest extends TestCase
{
    use CustomerTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    /**
     * @var callable
     */
    private $callbackFn;

    /**
     * @var array<mixed>
     */
    private array $events;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->assignSalesChannelContext($this->browser);

        $this->customerRepository = static::getContainer()->get('customer.repository');

        $this->callbackFn = function (Event $event): void {
            $this->events[$event::class] = $event;
        };

        $this->events = [];
    }

    public function testNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $response['errors'][0]['code']);
    }

    public function testDeleteAValidCustomer(): void
    {
        /** @var TraceableEventDispatcher $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');

        $this->addEventListener($dispatcher, CustomerDeletedEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(
            CustomerDeletedEvent::class,
            $this->events,
            'IndexStartEvent was dispatched but should not yet.'
        );

        $email = Uuid::randomHex() . '@example.com';
        $id = $this->createCustomer($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopwell',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/customer',
                [
                ]
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria([$id]);
        $customer = $this->customerRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNull($customer);

        static::assertArrayHasKey(CustomerDeletedEvent::class, $this->events);
        $customerDeletedEvent = $this->events[CustomerDeletedEvent::class];
        static::assertInstanceOf(CustomerDeletedEvent::class, $customerDeletedEvent);

        $dispatcher->removeListener(CustomerDeletedEvent::class, $this->callbackFn);
    }

    public function testDeleteGuestUser(): void
    {
        $customerId = $this->createCustomer(null, true);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->getLoggedInContextToken($customerId, $this->ids->get('sales-channel')));

        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/customer',
                [
                ]
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria([$customerId]);
        $customer = $this->customerRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNull($customer);
    }
}
