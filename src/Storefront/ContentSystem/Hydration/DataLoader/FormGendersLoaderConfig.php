<?php declare(strict_types=1);

namespace Shopwell\Storefront\ContentSystem\Hydration\DataLoader;

use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final readonly class FormGendersLoaderConfig extends AbstractContentDataLoaderConfig
{
    public function jsonSerialize(): array
    {
        return [];
    }
}
