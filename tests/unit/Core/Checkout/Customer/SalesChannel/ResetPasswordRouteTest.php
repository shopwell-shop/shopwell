<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Extension\ResetPasswordRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Shopwell\Core\Test\Generator;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ResetPasswordRoute::class)]
class ResetPasswordRouteTest extends TestCase
{
    public function testResetsAllRateLimitersOnPasswordReset(): void
    {
        $email = 'Customer@Example.com';
        $ip = '10.0.0.1';
        $hash = 'valid-hash';
        $customerId = Uuid::randomHex();
        $recoveryId = Uuid::randomHex();
        $expectedEmailKey = strtolower($email);
        $expectedCombinedKey = $expectedEmailKey . '-' . $ip;

        $customer = new CustomerEntity();
        $customer->setId($customerId);
        $customer->setEmail($email);
        $customer->setDoubleOptInRegistration(false);

        $recovery = new CustomerRecoveryEntity();
        $recovery->setId($recoveryId);
        $recovery->setCustomer($customer);
        $recovery->setCreatedAt(new \DateTimeImmutable());

        $recoveryCollection = new CustomerRecoveryCollection([$recovery]);

        $customerRecoveryRepository = static::createStub(EntityRepository::class);
        $customerRecoveryRepository->method('search')
            ->willReturn(new EntitySearchResult(
                'customer_recovery',
                1,
                $recoveryCollection,
                null,
                new Criteria(),
                static::createStub(SalesChannelContext::class)->getContext()
            ));

        $customerRepository = static::createStub(EntityRepository::class);

        $resetCalls = [];
        $resetIfConfiguredCalls = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))
            ->method('reset')
            ->willReturnCallback(function (string $route, string $key) use (&$resetCalls): void {
                $resetCalls[] = [$route, $key];
            });
        $rateLimiter->expects($this->exactly(2))
            ->method('resetIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$resetIfConfiguredCalls): void {
                $resetIfConfiguredCalls[] = [$route, $key];
            });

        $mainRequest = new Request(server: ['REMOTE_ADDR' => $ip]);
        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        $passwordValidationFactory = static::createStub(DataValidationFactoryInterface::class);
        $passwordValidationFactory->method('update')->willReturn(new DataValidationDefinition());

        $route = new ResetPasswordRoute(
            $customerRepository,
            $customerRecoveryRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            $requestStack,
            $rateLimiter,
            $passwordValidationFactory,
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->expects($this->exactly(5))->method('getContext')->willReturn(Context::createDefaultContext());

        $route->resetPassword(
            new RequestDataBag([
                'hash' => $hash,
                'newPassword' => 'newPass123!',
                'newPasswordConfirm' => 'newPass123!',
            ]),
            $context,
        );

        static::assertSame([
            [RateLimiter::LOGIN_ROUTE, $expectedCombinedKey],
            [RateLimiter::RESET_PASSWORD, $expectedCombinedKey],
        ], $resetCalls);

        static::assertSame([
            [RateLimiter::LOGIN_USER, $expectedEmailKey],
            [RateLimiter::LOGIN_CLIENT, $ip],
        ], $resetIfConfiguredCalls);
    }

    public function testConfirmsUnconfirmedDoubleOptInCustomerOnPasswordReset(): void
    {
        $now = new \DateTimeImmutable('2026-05-30 12:00:00');

        $customer = $this->createCustomer();
        $customer->setDoubleOptInRegistration(true);

        $customerUpdate = $this->resetPasswordAndReturnCustomerUpdate($customer, new MockClock($now));

        static::assertArrayHasKey('doubleOptInConfirmDate', $customerUpdate);
        static::assertEquals($now, $customerUpdate['doubleOptInConfirmDate']);
    }

    public function testDoesNotConfirmCustomerWithoutDoubleOptInRegistrationOnPasswordReset(): void
    {
        $customer = $this->createCustomer();
        $customer->setDoubleOptInRegistration(false);

        $customerUpdate = $this->resetPasswordAndReturnCustomerUpdate($customer);

        static::assertArrayNotHasKey('doubleOptInConfirmDate', $customerUpdate);
    }

    public function testDoesNotOverwriteExistingDoubleOptInConfirmationOnPasswordReset(): void
    {
        $customer = $this->createCustomer();
        $customer->setDoubleOptInRegistration(true);
        $customer->setDoubleOptInConfirmDate(new \DateTimeImmutable('2026-05-29 12:00:00'));

        $customerUpdate = $this->resetPasswordAndReturnCustomerUpdate($customer);

        static::assertArrayNotHasKey('doubleOptInConfirmDate', $customerUpdate);
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $context = Generator::generateSalesChannelContext();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('reset-password-route.reset-password.pre', static function (ResetPasswordRouteExtension $extension) use ($data, $context, $response): void {
            static::assertSame(['data' => $data, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ResetPasswordRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(RequestStack::class),
            static::createStub(RateLimiter::class),
            static::createStub(DataValidationFactoryInterface::class),
            static::createStub(ClockInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->resetPassword($data, $context));
    }

    private function createCustomer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setEmail('customer@example.com');

        return $customer;
    }

    /**
     * @return array<string, mixed>
     */
    private function resetPasswordAndReturnCustomerUpdate(CustomerEntity $customer, ?MockClock $clock = null): array
    {
        $hash = 'valid-hash';
        $recovery = new CustomerRecoveryEntity();
        $recovery->setId(Uuid::randomHex());
        $recovery->setHash($hash);
        $recovery->setCustomer($customer);
        $recovery->setCreatedAt(new \DateTimeImmutable());

        $customerRecoveryRepository = static::createStub(EntityRepository::class);
        $customerRecoveryRepository->method('search')
            ->willReturn(new EntitySearchResult(
                'customer_recovery',
                1,
                new CustomerRecoveryCollection([$recovery]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));
        $customerRecoveryRepository->method('delete')
            ->willReturn(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []));

        $customerUpdate = null;
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $updates) use (&$customerUpdate): EntityWrittenContainerEvent {
                $customerUpdate = $updates[0];

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
            });

        $passwordValidationFactory = static::createStub(DataValidationFactoryInterface::class);
        $passwordValidationFactory->method('update')->willReturn(new DataValidationDefinition());

        $route = new ResetPasswordRoute(
            $customerRepository,
            $customerRecoveryRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new RequestStack(),
            static::createStub(RateLimiter::class),
            $passwordValidationFactory,
            $clock ?? new MockClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $route->resetPassword(
            new RequestDataBag([
                'hash' => $hash,
                'newPassword' => 'newPass123!',
                'newPasswordConfirm' => 'newPass123!',
            ]),
            $context,
        );

        static::assertIsArray($customerUpdate);

        return $customerUpdate;
    }
}
