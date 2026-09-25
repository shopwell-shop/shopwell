<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Sitemap>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SitemapCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Sitemap::class;
    }
}
