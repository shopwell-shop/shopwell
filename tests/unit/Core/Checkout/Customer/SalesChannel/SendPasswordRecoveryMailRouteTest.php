<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Extension\SendPasswordRecoveryMailRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SendPasswordRecoveryMailRoute::class)]
class SendPasswordRecoveryMailRouteTest extends TestCase
{
    /**
     * @var EntityRepository<CustomerCollection>&Stub
     */
    protected EntityRepository&Stub $customerRepository;

    /**
     * @var EntityRepository<CustomerRecoveryCollection>&Stub
     */
    protected EntityRepository&Stub $customerRecoveryRepository;

    protected EventDispatcherInterface&Stub $eventDispatcher;

    protected DataValidator&Stub $validator;

    protected SystemConfigService&Stub $systemConfigService;

    protected RequestStack&Stub $requestStack;

    protected RateLimiter&Stub $rateLimiter;

    protected SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->customerRepository = static::createStub(EntityRepository::class);
        $this->customerRecoveryRepository = static::createStub(EntityRepository::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->systemConfigService = static::createStub(SystemConfigService::class);
        $this->requestStack = static::createStub(RequestStack::class);
        $this->rateLimiter = static::createStub(RateLimiter::class);
        $this->context = Generator::generateSalesChannelContext();
    }

    public function testSendRecoveryMail(): void
    {
        $customer = new CustomerEntity();
        $customer->setId('foo');

        $customerRecovery = new CustomerRecoveryEntity();
        $customerRecovery->setId('customer-recovery-id');
        $customerRecovery->setUniqueIdentifier('customer-recovery-id');
        $customerRecovery->setCustomerId($customer->getId());
        $customerRecovery->setHash('super-secret-hash');
        $customerRecovery->setCustomer($customer);

        $customerRepository = StaticEntityRepository::of(CustomerCollection::class, [[$customer->getId()]]);
        $customerRecoveryRepository = StaticEntityRepository::of(CustomerRecoveryCollection::class, [
            [$customerRecovery->getId()],
            new CustomerRecoveryCollection([$customerRecovery]),
        ]);

        $this->requestStack->method('getMainRequest')->willReturn(new Request(server: ['REMOTE_ADDR' => '10.0.0.1']));
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted')
            ->with(RateLimiter::RESET_PASSWORD, 'test@test.dev-10.0.0.1');

        $MailRoute = new SendPasswordRecoveryMailRoute(
            $customerRepository,
            $customerRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $rateLimiter,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->context->getSalesChannel()->setTranslated(['name' => 'FooBar']);

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnArgument(0);

        $data = new RequestDataBag();
        $data->set('email', " \u{00A0}Test@Test.DEV\u{3000}  ");
        $data->set('storefrontUrl', 'https://test.example.dev');

        $MailRoute->sendRecoveryMail($data, $this->context);

        static::assertSame([[['id' => $customerRecovery->getId()]]], $customerRecoveryRepository->deletes);
        static::assertCount(1, $customerRecoveryRepository->creates);
        static::assertCount(1, $customerRecoveryRepository->creates[0]);

        $recoveryData = $customerRecoveryRepository->creates[0][0];
        static::assertSame('foo', $recoveryData['customerId']);
        static::assertSame(32, \strlen($recoveryData['hash']));
    }

    public function testNoCustomerFound(): void
    {
        $mailRoute = new SendPasswordRecoveryMailRoute(
            $this->customerRepository,
            $this->customerRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $data = new RequestDataBag();
        $data->set('email', 'foo@foo');

        $response = $mailRoute->sendRecoveryMail($data, $this->context)->getObject()->getVars();

        static::assertArrayHasKey('success', $response);
        static::assertTrue($response['success']);
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('send-password-recovery-mail-route.send-recovery-mail.pre', function (SendPasswordRecoveryMailRouteExtension $extension) use ($data, $response): void {
            static::assertSame(['data' => $data, 'context' => $this->context, 'validateStorefrontUrl' => true], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SendPasswordRecoveryMailRoute(
            $this->customerRepository,
            $this->customerRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter,
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->sendRecoveryMail($data, $this->context, true));
    }
}
