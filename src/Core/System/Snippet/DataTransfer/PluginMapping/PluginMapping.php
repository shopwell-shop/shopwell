<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\DataTransfer\PluginMapping;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class PluginMapping
{
    public function __construct(
        public string $pluginName,
        public string $snippetName,
    ) {
    }
}
