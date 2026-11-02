<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('checkout')]
class MembershipExpirationTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'membership.expiration';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
