<?php

declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * This event can be used to react to the creation of a new context.
 * It must be used very carefully, as it practically effects every part of Shopwell.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ContextCreatedEvent
{
    public function __construct(
        public Context $context,
    ) {
    }
}
