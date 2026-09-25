<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\Service;

use Shopwell\Core\Content\Sitemap\SitemapException;
use Shopwell\Core\Content\Sitemap\Struct\SitemapGenerationResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('discovery')]
interface SitemapExporterInterface
{
    public const SITEMAP_URL_LIMIT = 49999;

    public const STRATEGY_MANUAL = 1;
    public const STRATEGY_SCHEDULED_TASK = 2;
    public const STRATEGY_LIVE = 3;

    /**
     * @throws SitemapException
     */
    public function generate(SalesChannelContext $context, bool $force = false, ?string $lastProvider = null, ?int $offset = null): SitemapGenerationResult;
}
