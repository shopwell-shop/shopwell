<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopwell\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerAccountRecoverRequestEvent::class)]
class CustomerAccountRecoverRequestEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setTranslated(['name' => 'my-shop-name']);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);

        $event = new CustomerAccountRecoverRequestEvent(
            $context,
            new CustomerRecoveryEntity(),
            'my-reset-url'
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('resetUrl', $flow->data());
        static::assertArrayHasKey('shopName', $flow->data());
        static::assertSame('my-reset-url', $flow->data()['resetUrl']);
        static::assertSame('my-shop-name', $flow->data()['shopName']);
    }
}
