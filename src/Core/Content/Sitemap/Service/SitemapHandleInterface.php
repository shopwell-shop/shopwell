<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\Service;

use Shopwell\Core\Content\Sitemap\Struct\Url;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
interface SitemapHandleInterface
{
    /**
     * @param list<Url> $urls
     */
    public function write(array $urls): void;

    public function finish(): void;
}
