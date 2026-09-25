<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerDoubleOptInRegistrationEvent::class)]
class CustomerDoubleOptInRegistrationEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $event = new CustomerDoubleOptInRegistrationEvent(
            new CustomerEntity(),
            static::createStub(SalesChannelContext::class),
            'my-confirm-url'
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('confirmUrl', $flow->data());
        static::assertSame('my-confirm-url', $flow->data()['confirmUrl']);
    }

    public function testCrud(): void
    {
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $customer->setId('test-id');

        $event = new CustomerDoubleOptInRegistrationEvent($customer, $context, 'my-confirm-url');

        static::assertSame('my-confirm-url', $event->getConfirmUrl());
        static::assertSame($context, $event->getSalesChannelContext());
        static::assertSame($customer, $event->getCustomer());
        static::assertSame($context->getSalesChannelId(), $event->getSalesChannelId());
        static::assertSame($context->getContext(), $event->getContext());
        static::assertSame('test-id', $event->getCustomerId());
    }
}
