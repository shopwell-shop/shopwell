<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ThemeSalesChannel>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ThemeSalesChannelCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ThemeSalesChannel::class;
    }
}
