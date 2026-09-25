<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Package('discovery')]
class SitemapGenerateTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'shopwell.sitemap_generate';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return (bool) $bag->get('shopwell.sitemap.scheduled_task.enabled');
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
