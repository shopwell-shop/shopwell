<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\Exception;

use Shopwell\Core\Content\Sitemap\SitemapException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class AlreadyLockedException extends SitemapException
{
    public function __construct(SalesChannelContext $salesChannelContext)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::SITEMAP_ALREADY_LOCKED,
            'Cannot acquire lock for sales channel {{salesChannelId}} and language {{languageId}}',
            [
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
                'languageId' => $salesChannelContext->getLanguageId(),
            ],
        );
    }
}
