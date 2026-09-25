<?php
declare(strict_types=1);

namespace Shopwell\Core\Framework\RateLimiter;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\NoLimiter;

#[Package('framework')]
class NoLimitRateLimiterFactory extends RateLimiterFactory
{
    public function __construct(private readonly RateLimiterFactory $rateLimiterFactory)
    {
    }

    public function create(?string $key = null): LimiterInterface
    {
        if (DisableRateLimiterCompilerPass::isDisabled()) {
            return new NoLimiter();
        }

        return $this->rateLimiterFactory->create($key);
    }
}
