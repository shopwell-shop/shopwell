<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Update\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Struct\ExtensionCollection;
use Shopwell\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopwell\Core\Framework\Update\Struct\Version;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @phpstan-import-type Compatibility from ExtensionCompatibility
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ExtensionCompatibilitiesResolvedEvent extends Event
{
    /**
     * @param list<Compatibility> $compatibilities
     */
    public function __construct(
        public Version $update,
        public ExtensionCollection $extensions,
        public array $compatibilities,
        public readonly Context $context
    ) {
    }
}
