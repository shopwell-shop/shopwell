<?php declare(strict_types=1);

namespace Shopwell\Core\Service\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
readonly class ServiceUpdatedEvent implements ShopwellEvent
{
    public function __construct(public string $service, private Context $context)
    {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
