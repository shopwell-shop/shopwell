<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopwell\Core\Checkout\Customer\Service\DoubleOptInService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
class DoubleOptInServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testResendDoubleOptInMailSendsEmailWhenExpired(): void
    {
        $customer = $this->createCustomer('c1', [
            'hash' => 'testhash',
            'guest' => false,
            'doubleOptInEmailSentDate' => new \DateTime('-2 days'),
        ]);
        $context = Generator::generateSalesChannelContext();

        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInResendInterval', 24, TestDefaults::SALES_CHANNEL);
        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInDomain', 'https://shop.example.com', TestDefaults::SALES_CHANNEL);

        $dispatched = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService($eventDispatcher)->resendDoubleOptInMail($customer, $context);

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);

        $updatedCustomer = $this->fetchCustomer($customer->getId());
        static::assertNotNull($updatedCustomer->getDoubleOptInEmailSentDate());
        static::assertGreaterThan(new \DateTimeImmutable('-5 minutes'), $updatedCustomer->getDoubleOptInEmailSentDate());
    }

    public function testResendDoubleOptInMailUpdatesSentDateAfterSending(): void
    {
        $originalSentDate = new \DateTime('-2 days');
        $customer = $this->createCustomer('c1', [
            'hash' => 'testhash',
            'guest' => false,
            'doubleOptInEmailSentDate' => $originalSentDate,
        ]);
        $context = Generator::generateSalesChannelContext();

        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInResendInterval', 24, TestDefaults::SALES_CHANNEL);
        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInDomain', 'https://shop.example.com', TestDefaults::SALES_CHANNEL);

        $customerId = $customer->getId();
        $sentDateAtDispatch = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            function () use (&$sentDateAtDispatch, $customerId): void {
                $sentDateAtDispatch = $this->fetchCustomer($customerId)->getDoubleOptInEmailSentDate();
            }
        );

        $this->createService($eventDispatcher)->resendDoubleOptInMail($customer, $context);

        static::assertNotNull($sentDateAtDispatch);
        static::assertLessThan(new \DateTimeImmutable('-1 day'), $sentDateAtDispatch);

        $updatedCustomer = $this->fetchCustomer($customerId);
        static::assertNotNull($updatedCustomer->getDoubleOptInEmailSentDate());
        static::assertGreaterThan(new \DateTimeImmutable('-5 minutes'), $updatedCustomer->getDoubleOptInEmailSentDate());
    }

    public function testResolveDomainUrlFromSystemConfig(): void
    {
        $customer = $this->createCustomer('c1', [
            'hash' => 'testhash',
            'guest' => false,
            'doubleOptInEmailSentDate' => new \DateTime('-2 days'),
        ]);
        $context = Generator::generateSalesChannelContext();

        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInResendInterval', 24, TestDefaults::SALES_CHANNEL);
        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInDomain', 'https://configured-domain.example.com', TestDefaults::SALES_CHANNEL);

        $dispatched = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService($eventDispatcher)->resendDoubleOptInMail($customer, $context);

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://configured-domain.example.com', $dispatched->getConfirmUrl());
    }

    public function testResolveDomainUrlFallsBackToRepository(): void
    {
        $customer = $this->createCustomer('c1', [
            'hash' => 'testhash',
            'guest' => false,
            'doubleOptInEmailSentDate' => new \DateTime('-2 days'),
        ]);
        $context = Generator::generateSalesChannelContext();

        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInResendInterval', 24, TestDefaults::SALES_CHANNEL);
        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInDomain', '', TestDefaults::SALES_CHANNEL);

        $domain = static::getContainer()->get('sales_channel_domain.repository')->search(
            (new Criteria())->addFilter(new EqualsFilter('salesChannelId', TestDefaults::SALES_CHANNEL))->setLimit(1),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(SalesChannelDomainEntity::class, $domain);

        $dispatched = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService($eventDispatcher)->resendDoubleOptInMail($customer, $context);

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertNotSame('', $domain->getUrl());
        static::assertStringStartsWith($domain->getUrl(), $dispatched->getConfirmUrl());
    }

    private function createService(EventDispatcher $eventDispatcher): DoubleOptInService
    {
        return new DoubleOptInService(
            static::getContainer()->get('customer.repository'),
            $eventDispatcher,
            $this->getSystemConfig(),
            static::getContainer()->get('sales_channel_domain.repository'),
            new NativeClock(),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createCustomer(string $key, array $overrides = []): CustomerEntity
    {
        $builder = new CustomerBuilder($this->ids, $key);
        foreach ($overrides as $field => $value) {
            $builder->add($field, $value);
        }

        $customerRepo = static::getContainer()->get('customer.repository');
        $customerRepo->create([$builder->build()], Context::createDefaultContext());

        return $this->fetchCustomer($this->ids->get($key));
    }

    private function fetchCustomer(string $id): CustomerEntity
    {
        $customer = static::getContainer()->get('customer.repository')
            ->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()
            ->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);

        return $customer;
    }

    private function getSystemConfig(): SystemConfigService
    {
        return static::getContainer()->get(SystemConfigService::class);
    }
}
