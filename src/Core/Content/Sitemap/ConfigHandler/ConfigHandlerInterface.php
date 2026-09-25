<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\ConfigHandler;

use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
interface ConfigHandlerInterface
{
    /**
     * @return array<string, array<array<string, mixed>>>
     */
    public function getSitemapConfig(): array;
}
