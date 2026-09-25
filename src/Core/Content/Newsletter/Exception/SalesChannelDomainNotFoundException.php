<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class SalesChannelDomainNotFoundException extends ShopwellHttpException
{
    public function __construct(SalesChannelEntity $salesChannel)
    {
        parent::__construct(
            'No domain found for sales channel {{ salesChannel }}',
            ['salesChannel' => $salesChannel->getTranslation('name')]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__SALES_CHANNEL_DOMAIN_NOT_FOUND';
    }
}
