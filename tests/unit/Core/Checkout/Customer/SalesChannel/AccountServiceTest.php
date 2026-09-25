<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopwell\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopwell\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopwell\Core\Checkout\Customer\Exception\CustomerNotFoundByIdException;
use Shopwell\Core\Checkout\Customer\Exception\CustomerOptinNotCompletedException;
use Shopwell\Core\Checkout\Customer\Exception\PasswordPoliciesUpdatedException;
use Shopwell\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopwell\Core\Checkout\Customer\SalesChannel\AbstractSwitchDefaultAddressRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Checkout\Customer\Service\DoubleOptInService;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;
use Shopwell\Core\System\SalesChannel\Context\CartRestorer;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountService::class)]
class AccountServiceTest extends TestCase
{
    public function testLoginByValidCredentials(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setPassword(TestDefaults::HASHED_PASSWORD);
        $customer->setEmail('foo@bar.de');
        $customer->setDoubleOptInRegistration(false);

        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ),
        ]);

        $loggedinSalesChannelContext = Generator::generateSalesChannelContext();
        $cartRestorer = $this->createMock(CartRestorer::class);
        $cartRestorer->expects($this->once())
            ->method('restore')
            ->willReturn($loggedinSalesChannelContext);

        $beforeLoginEventCalled = false;
        $loginEventCalled = false;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            CustomerBeforeLoginEvent::class,
            static function (CustomerBeforeLoginEvent $event) use ($salesChannelContext, &$beforeLoginEventCalled): void {
                $beforeLoginEventCalled = true;
                static::assertSame('foo@bar.de', $event->getEmail());
                static::assertSame($salesChannelContext, $event->getSalesChannelContext());
            },
        );

        $eventDispatcher->addListener(
            CustomerLoginEvent::class,
            static function (CustomerLoginEvent $event) use ($customer, $loggedinSalesChannelContext, &$loginEventCalled): void {
                $loginEventCalled = true;
                static::assertSame($customer, $event->getCustomer());
                static::assertSame($loggedinSalesChannelContext, $event->getSalesChannelContext());
                static::assertSame($loggedinSalesChannelContext->getToken(), $event->getContextToken());
            },
        );

        $accountService = new AccountService(
            $customerRepository,
            $eventDispatcher,
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            $cartRestorer,
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        $token = $accountService->loginByCredentials('foo@bar.de', 'shopwell', $salesChannelContext);
        static::assertSame($loggedinSalesChannelContext->getToken(), $token);
        static::assertTrue($beforeLoginEventCalled);
        static::assertTrue($loginEventCalled);
        static::assertCount(1, $customerRepository->updates);
        static::assertCount(1, $customerRepository->updates[0]);
        static::assertIsArray($customerRepository->updates[0][0]);
        static::assertCount(2, $customerRepository->updates[0][0]);
        static::assertSame($customer->getId(), $customerRepository->updates[0][0]['id']);
        static::assertInstanceOf(\DateTimeImmutable::class, $customerRepository->updates[0][0]['lastLogin']);
    }

    public function testLoginFailsByInvalidCredentials(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setPassword(TestDefaults::HASHED_PASSWORD);
        $customer->setEmail('foo@bar.de');
        $customer->setDoubleOptInRegistration(false);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $cartRestorer = $this->createMock(CartRestorer::class);
        $cartRestorer->expects($this->never())
            ->method('restore');

        $accountService = new AccountService(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            $cartRestorer,
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        $this->expectException(BadCredentialsException::class);
        $accountService->loginByCredentials('foo@bar.de', 'invalidPassword', $salesChannelContext);
    }

    public function testGetCustomerByLoginThrowsBadCredentialsWhenEmailNotFound(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                0,
                new CustomerCollection(),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $accountService = new AccountService(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        $this->expectException(BadCredentialsException::class);
        $accountService->getCustomerByLogin('unknown@example.com', 'any-password', $salesChannelContext);
    }

    public function testGetCustomerByIdThrowsPasswordPoliciesChangedException(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setLegacyPassword('foo');
        $customer->setLegacyEncoder('bar');

        $legacyPasswordVerifier = $this->createMock(LegacyPasswordVerifier::class);
        $legacyPasswordVerifier->expects($this->once())
            ->method('verify')
            ->with('password', $customer)
            ->willReturn(true);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $exception = new WriteConstraintViolationException(new ConstraintViolationList([new ConstraintViolation('', '', [], '', '/password', '')]), '/');
        $writeException = new WriteException();
        $writeException->add($exception);

        $customerRepository->expects($this->once())
            ->method('update')
            ->with([[
                'id' => $customer->getId(),
                'password' => 'password',
                'legacyPassword' => null,
                'legacyEncoder' => null,
            ]], $salesChannelContext->getContext())
            ->willThrowException($writeException);

        $accountService = new AccountService(
            $customerRepository,
            static::createStub(EventDispatcherInterface::class),
            $legacyPasswordVerifier,
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        $this->expectExceptionObject(new PasswordPoliciesUpdatedException());
        $accountService->getCustomerByLogin('user', 'password', $salesChannelContext);
    }

    public function testGetCustomerByIdIgnoresOtherWriteViolations(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setLegacyPassword('foo');
        $customer->setLegacyEncoder('bar');

        $legacyPasswordVerifier = $this->createMock(LegacyPasswordVerifier::class);
        $legacyPasswordVerifier->expects($this->once())
            ->method('verify')
            ->with('password', $customer)
            ->willReturn(true);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $exception = CustomerException::badCredentials();
        $writeException = new WriteException();
        $writeException->add($exception);

        $customerRepository->expects($this->once())
            ->method('update')
            ->with([[
                'id' => $customer->getId(),
                'password' => 'password',
                'legacyPassword' => null,
                'legacyEncoder' => null,
            ]], $salesChannelContext->getContext())
            ->willThrowException($writeException);

        $accountService = new AccountService(
            $customerRepository,
            static::createStub(EventDispatcherInterface::class),
            $legacyPasswordVerifier,
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        $this->expectException(WriteException::class);
        $accountService->getCustomerByLogin('user', 'password', $salesChannelContext);
    }

    public function testSetDefaultBillingAddress(): void
    {
        $context = Generator::generateSalesChannelContext();
        $customer = $context->getCustomer();

        static::assertNotNull($customer);

        $switcher = $this->createMock(AbstractSwitchDefaultAddressRoute::class);
        $switcher
            ->expects($this->once())
            ->method('swap')
            ->with('billing-address-id', AbstractSwitchDefaultAddressRoute::TYPE_BILLING, $context, $customer);

        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            $switcher,
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        $accountService->setDefaultBillingAddress('billing-address-id', $context, $customer);
    }

    public function testSetDefaultShippingAddress(): void
    {
        $context = Generator::generateSalesChannelContext();
        $customer = $context->getCustomer();

        static::assertNotNull($customer);

        $switcher = $this->createMock(AbstractSwitchDefaultAddressRoute::class);
        $switcher
            ->expects($this->once())
            ->method('swap')
            ->with('shipping-address-id', AbstractSwitchDefaultAddressRoute::TYPE_SHIPPING, $context, $customer);

        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            $switcher,
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        $accountService->setDefaultShippingAddress('shipping-address-id', $context, $customer);
    }

    public function testLoginById(): void
    {
        $context = Generator::generateSalesChannelContext();

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setBoundSalesChannelId($context->getSalesChannel()->getId());
        $customer->setEmail('foo@bar.de');

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $context->getContext()
            ));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::callback(static function ($event) use ($context, $customer): bool {
                if ($event instanceof CustomerBeforeLoginEvent) {
                    static::assertSame($context, $event->getSalesChannelContext());
                    static::assertSame($customer->getEmail(), $event->getEmail());

                    return true;
                }

                if ($event instanceof CustomerLoginEvent) {
                    static::assertSame($customer, $event->getCustomer());

                    return true;
                }

                return false;
            }));

        $accountService = new AccountService(
            $repo,
            $dispatcher,
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        $accountService->loginById($customer->getId(), $context);
    }

    public function testLoginByIdWithNonValidId(): void
    {
        $context = Generator::generateSalesChannelContext();

        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        $this->expectException(BadCredentialsException::class);

        $accountService->loginById('foo', $context);
    }

    public function testLoginByIdNotFound(): void
    {
        $context = Generator::generateSalesChannelContext();

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                0,
                new CustomerCollection(),
                null,
                new Criteria(),
                $context->getContext()
            ));

        $accountService = new AccountService(
            $repo,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        $this->expectException(CustomerNotFoundByIdException::class);

        $accountService->loginById(Uuid::randomHex(), $context);
    }

    public function testPasswordTooLongThrowsBadCredentials(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        static::expectException(BadCredentialsException::class);

        $accountService->loginByCredentials('foo@bar.de', \str_repeat('a', PasswordHasherInterface::MAX_PASSWORD_LENGTH + 1), $salesChannelContext);
    }

    public function testGetCustomerByLoginWithUnconfirmedDoubleOptIn(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setPassword(TestDefaults::HASHED_PASSWORD);
        $customer->setEmail('foo@bar.de');
        $customer->setDoubleOptInRegistration(true);

        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ),
        ]);

        $doubleOptInService = $this->createMock(DoubleOptInService::class);
        $doubleOptInService->expects($this->once())
            ->method('resendDoubleOptInMail')
            ->with($customer, $salesChannelContext);

        $accountService = new AccountService(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            $doubleOptInService,
            new NativeClock(),
        );

        $this->expectException(CustomerOptinNotCompletedException::class);
        $accountService->getCustomerByLogin('foo@bar.de', 'shopwell', $salesChannelContext);
    }
}
