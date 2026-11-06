<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Membership\ScheduledTask\MembershipExpirationTask;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipExpirationTask::class)]
class MembershipExpirationTaskTest extends TestCase
{
    public function testExpiresMembershipsDailyAndReschedulesFailures(): void
    {
        static::assertSame('membership.expiration', MembershipExpirationTask::getTaskName());
        static::assertSame(86400, MembershipExpirationTask::getDefaultInterval());
        static::assertTrue(MembershipExpirationTask::shouldRescheduleOnFailure());
    }
}
