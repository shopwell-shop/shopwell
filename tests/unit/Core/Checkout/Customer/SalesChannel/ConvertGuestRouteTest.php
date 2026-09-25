<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ConvertGuestRoute::class)]
class ConvertGuestRouteTest extends TestCase
{
    private ConvertGuestRoute $route;

    /**
     * @var StaticEntityRepository<CustomerCollection>
     */
    private StaticEntityRepository $customerRepository;

    private EventDispatcherInterface&Stub $eventDispatcher;

    private DataValidator&Stub $validator;

    private DataValidationFactoryInterface&Stub $passwordValidationFactory;

    private SalesChannelContext $salesChannelContext;

    private CustomerEntity $customer;

    protected function setUp(): void
    {
        $this->customerRepository = new StaticEntityRepository([]);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->passwordValidationFactory = static::createStub(DataValidationFactoryInterface::class);

        $this->route = $this->buildRoute();

        $this->salesChannelContext = Generator::generateSalesChannelContext();

        $this->customer = new CustomerEntity();
        $this->customer->setId('test-customer-id');
        $this->customer->setEmail('test@example.com');
        $this->customer->setGuest(true);
    }

    public function testConvertGuestSuccess(): void
    {
        $requestDataBag = new RequestDataBag(['password' => 'new-password']);

        $passwordDefinition = new DataValidationDefinition('customer.password');
        $passwordDefinition->add('password', new NotBlank());

        $passwordValidationFactory = $this->createMock(DataValidationFactoryInterface::class);
        $passwordValidationFactory->expects($this->once())
            ->method('create')
            ->with($this->salesChannelContext)
            ->willReturn($passwordDefinition);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                static::anything(),
                static::equalTo('framework.validation.customer.guest.convert')
            );

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with([
                'email' => 'test@example.com',
                'password' => 'new-password',
            ], static::callback(function (DataValidationDefinition $definition) {
                static::assertSame('customer.guest.convert', $definition->getName());
                static::assertEquals([
                    'password' => [new NotBlank()],
                    'email' => [new CustomerEmailUnique(salesChannelContext: $this->salesChannelContext)],
                ], $definition->getProperties());

                return true;
            }));

        $route = $this->buildRoute($eventDispatcher, $validator, $passwordValidationFactory);
        $route->convertGuest($requestDataBag, $this->salesChannelContext, $this->customer);

        static::assertSame([[[
            'id' => 'test-customer-id',
            'email' => 'test@example.com',
            'guest' => false,
            'password' => 'new-password',
        ]]], $this->customerRepository->updates);
    }

    public function testConvertGuestFailsForRegisteredCustomer(): void
    {
        $this->customer->setGuest(false);
        $requestDataBag = new RequestDataBag(['password' => 'new-password']);

        $this->expectExceptionObject(CustomerException::registeredCustomerCannotBeConverted('test-customer-id'));

        $this->route->convertGuest($requestDataBag, $this->salesChannelContext, $this->customer);
    }

    public function testConvertGuestFailsWithValidationErrors(): void
    {
        $requestDataBag = new RequestDataBag(['password' => '']);

        $passwordDefinition = new DataValidationDefinition('customer.password');
        $passwordDefinition->add('password', new NotBlank());

        $passwordValidationFactory = $this->createMock(DataValidationFactoryInterface::class);
        $passwordValidationFactory->expects($this->once())
            ->method('create')
            ->with($this->salesChannelContext)
            ->willReturn($passwordDefinition);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch');

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with([
                'email' => 'test@example.com',
                'password' => '',
            ], static::callback(function (DataValidationDefinition $definition) {
                static::assertSame('customer.guest.convert', $definition->getName());
                static::assertEquals([
                    'password' => [new NotBlank()],
                    'email' => [new CustomerEmailUnique(salesChannelContext: $this->salesChannelContext)],
                ], $definition->getProperties());

                return true;
            }))
            ->willThrowException(new ConstraintViolationException(
                new ConstraintViolationList(),
                [
                    'id' => 'test-customer-id',
                    'guest' => false,
                    'password' => '',
                    'email' => 'test@example.com']
            ));

        $route = $this->buildRoute($eventDispatcher, $validator, $passwordValidationFactory);

        $this->expectException(ConstraintViolationException::class);
        $route->convertGuest($requestDataBag, $this->salesChannelContext, $this->customer);

        static::assertEmpty($this->customerRepository->updates);
    }

    private function buildRoute(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?DataValidator $validator = null,
        ?DataValidationFactoryInterface $passwordValidationFactory = null
    ): ConvertGuestRoute {
        return new ConvertGuestRoute(
            $this->customerRepository,
            $eventDispatcher ?? $this->eventDispatcher,
            $validator ?? $this->validator,
            $passwordValidationFactory ?? $this->passwordValidationFactory,
            static::createStub(RequestStack::class),
            static::createStub(RateLimiter::class),
        );
    }
}
