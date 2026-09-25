<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('discovery')]
interface SitemapHandleFactoryInterface
{
    public function create(
        FilesystemOperator $filesystem,
        SalesChannelContext $context,
        ?string $domain = null,
        ?string $domainId = null,
    ): SitemapHandleInterface;
}
