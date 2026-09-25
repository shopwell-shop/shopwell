<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Struct\ExtensionCollection;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class InstalledExtensionsListingLoadedEvent extends Event
{
    public function __construct(public ExtensionCollection $extensionCollection, public readonly Context $context)
    {
    }
}
