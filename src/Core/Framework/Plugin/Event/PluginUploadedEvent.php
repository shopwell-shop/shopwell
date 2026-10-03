<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class PluginUploadedEvent extends Event
{
    public function __construct(
        public readonly string $filename,
        public readonly Context $context,
        public readonly ?string $pluginName = null,
        public readonly ?string $pluginVersion = null
    ) {
    }
}
