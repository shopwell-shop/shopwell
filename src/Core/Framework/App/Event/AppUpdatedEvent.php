<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Event;

use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class AppUpdatedEvent extends ManifestChangedEvent
{
    final public const NAME = 'app.updated';

    public function getName(): string
    {
        return self::NAME;
    }
}
