<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Rule;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\Clock;

#[Package('fundamentals@after-sales')]
abstract class RuleScope
{
    abstract public function getContext(): Context;

    abstract public function getSalesChannelContext(): SalesChannelContext;

    public function getCurrentTime(): \DateTimeImmutable
    {
        return Clock::get()->now();
    }
}
