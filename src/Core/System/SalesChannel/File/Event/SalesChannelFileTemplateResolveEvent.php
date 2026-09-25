<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\File\Event;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class SalesChannelFileTemplateResolveEvent extends Event
{
    public function __construct(public readonly string $salesChannelId)
    {
    }
}
