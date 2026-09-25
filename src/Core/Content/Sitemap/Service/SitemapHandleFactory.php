<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('discovery')]
class SitemapHandleFactory implements SitemapHandleFactoryInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function create(
        FilesystemOperator $filesystem,
        SalesChannelContext $context,
        ?string $domain = null,
        ?string $domainId = null,
    ): SitemapHandleInterface {
        $domainId = \func_num_args() > 3 ? func_get_arg(3) : null;

        return new SitemapHandle($filesystem, $context, $this->eventDispatcher, $domain, $domainId);
    }
}
