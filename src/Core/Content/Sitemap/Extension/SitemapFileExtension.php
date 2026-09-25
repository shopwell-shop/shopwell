<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<Response>
 */
#[Package('discovery')]
final class SitemapFileExtension extends Extension
{
    public const NAME = 'sitemap.get-file';

    /**
     * @internal
     */
    public function __construct(
        /**
         * @public
         *
         * @description Allows you to access to the current request
         */
        public readonly Request $request,

        /**
         * @public
         *
         * @description Allows you to access to the current customer/sales-channel context
         */
        public readonly SalesChannelContext $context,

        /**
         * @public
         *
         * @description The file path of the requested sitemap file
         */
        public readonly string $filePath
    ) {
    }
}
