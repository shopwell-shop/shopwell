<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\HealthCheck\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class HealthCheckEvent extends Event
{
    public function __construct(
        public readonly Context $context
    ) {
    }
}
