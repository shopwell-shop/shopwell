<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Event\PaymentEventDispatcher;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Event acknowledgements use their own repositories so domain tests observe settlement writes alone.
 *
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
final class PaymentEventDispatcherFixture extends TestCase
{
    public static function create(
        PaymentWebhookDispatcher $webhooks,
        ?EventDispatcherInterface $events = null,
        ?ClockInterface $clock = null,
    ): PaymentEventDispatcher {
        return new PaymentEventDispatcher(
            StaticEntityRepository::of(PaymentOrderCollection::class),
            StaticEntityRepository::of(PaymentRefundCollection::class),
            $webhooks,
            $events ?? static::createStub(EventDispatcherInterface::class),
            $clock ?? new MockClock('2026-01-01'),
            new NullLogger(),
        );
    }
}
