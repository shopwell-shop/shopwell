<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Checkout\Membership\ScheduledTask\MembershipExpirationTaskHandler;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipExpirationTaskHandler::class)]
class MembershipExpirationTaskHandlerTest extends TestCase
{
    public function testDelegatesExpiryToTheSubscriptionServiceWithSystemContext(): void
    {
        $subscriptions = $this->createMock(MembershipSubscriptionService::class);
        $subscriptions->expects($this->once())->method('expireDue')->with(static::callback(
            static fn (Context $context): bool => $context->getScope() === Context::SYSTEM_SCOPE,
        ));
        (new MembershipExpirationTaskHandler(static::createStub(EntityRepository::class), new NullLogger(), $subscriptions))->run();
    }
}
