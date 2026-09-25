<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('after-sales')]
class NewsletterRecipientTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'delete_newsletter_recipient_task';
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
