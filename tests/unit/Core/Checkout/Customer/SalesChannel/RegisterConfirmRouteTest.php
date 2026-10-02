<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Exception\CustomerAlreadyConfirmedException;
use Shopwell\Core\Checkout\Customer\Extension\RegisterConfirmRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopwell\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RegisterConfirmRoute::class)]
class RegisterConfirmRouteTest extends TestCase
{
    protected SalesChannelContext&Stub $context;

    protected EventDispatcherInterface&Stub $eventDispatcher;

    /**
     * @var EntityRepository<CustomerCollection>&MockObject
     */
    protected EntityRepository&MockObject $customerRepository;

    protected DataValidator&Stub $validator;

    protected SalesChannelContextPersister&Stub $salesChannelContextPersister;

    protected SalesChannelContextServiceInterface&Stub $salesChannelContextService;

    protected RegisterConfirmRoute $route;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = static::createStub(SalesChannelContext::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->customerRepository = $this->createMock(EntityRepository::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->salesChannelContextPersister = static::createStub(SalesChannelContextPersister::class);

        $newSalesChannelContext = static::createStub(SalesChannelContext::class);
        $newSalesChannelContext->method('getCustomer')->willReturn(new CustomerEntity());

        $this->salesChannelContextService = static::createStub(SalesChannelContextServiceInterface::class);
        $this->salesChannelContextService
            ->method('get')
            ->willReturn($newSalesChannelContext);

        $this->route = $this->createRoute();
    }

    public function testConfirmCustomer(): void
    {
        $customer = $this->mockCustomer();

        $this->customerRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer',
                    1,
                    new CustomerCollection([$customer]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $confirmResult = $this->route->confirm($this->mockRequestDataBag(), $this->context);

        static::assertTrue($confirmResult->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testConfirmCustomerNotDoubleOptIn(): void
    {
        $customer = $this->mockCustomer();
        $customer->setDoubleOptInRegistration(false);

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer',
                    1,
                    new CustomerCollection([$customer]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->willReturnCallback(static function (array $data, DataValidationDefinition $definition): void {
                $properties = $definition->getProperties();
                static::assertArrayHasKey('doubleOptInRegistration', $properties);
                static::assertContainsOnlyInstancesOf(IsTrue::class, $properties['doubleOptInRegistration']);

                static::assertFalse($data['doubleOptInRegistration']);

                throw new ConstraintViolationException(new ConstraintViolationList(), $data);
            });

        $route = $this->createRoute($validator);

        static::expectException(ConstraintViolationException::class);
        $route->confirm($this->mockRequestDataBag(), $this->context);
    }

    public function testConfirmActivatedCustomer(): void
    {
        $customer = $this->mockCustomer();
        $customer->setActive(true);
        $customer->setDoubleOptInConfirmDate(new \DateTime());

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer',
                    1,
                    new CustomerCollection([$customer]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        static::expectException(CustomerAlreadyConfirmedException::class);
        $this->route->confirm($this->mockRequestDataBag(), $this->context);
    }

    public function testConfirmConfirmedCustomer(): void
    {
        $customer = $this->mockCustomer();
        $customer->setDoubleOptInConfirmDate(new \DateTime());

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer',
                    1,
                    new CustomerCollection([$customer]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        static::expectException(CustomerAlreadyConfirmedException::class);
        $this->route->confirm($this->mockRequestDataBag(), $this->context);
    }

    public function testPublishesExtension(): void
    {
        $dataBag = new RequestDataBag();
        $response = new CustomerResponse(new CustomerEntity());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('register-confirm-route.confirm.pre', function (RegisterConfirmRouteExtension $extension) use ($dataBag, $response): void {
            static::assertSame(['dataBag' => $dataBag, 'context' => $this->context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $this->customerRepository->expects($this->never())->method('search');

        $route = new RegisterConfirmRoute(
            $this->customerRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(SalesChannelContextPersister::class),
            static::createStub(SalesChannelContextServiceInterface::class),
            static::createStub(ClockInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->confirm($dataBag, $this->context));
    }

    protected function mockCustomer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-1');
        $customer->setActive(false);
        $customer->setEmail('test@test.test');
        $customer->setHash('hash');
        $customer->setGuest(false);
        $customer->setDoubleOptInRegistration(true);
        $customer->setDoubleOptInEmailSentDate(new \DateTime());

        return $customer;
    }

    protected function mockRequestDataBag(): RequestDataBag
    {
        return new RequestDataBag([
            'hash' => 'hash',
            'em' => Hasher::hash('test@test.test', 'sha1'),
        ]);
    }

    private function createRoute(?DataValidator $validator = null): RegisterConfirmRoute
    {
        return new RegisterConfirmRoute(
            $this->customerRepository,
            $this->eventDispatcher,
            $validator ?? $this->validator,
            $this->salesChannelContextPersister,
            $this->salesChannelContextService,
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher())
        );
    }
}
