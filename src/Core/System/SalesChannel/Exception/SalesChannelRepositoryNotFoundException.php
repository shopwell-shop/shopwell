<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelRepositoryNotFoundException extends ShopwellHttpException
{
    public function __construct(string $entity)
    {
        parent::__construct(
            'SalesChannelRepository for entity "{{ entityName }}" does not exist.',
            ['entityName' => $entity]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__SALES_CHANNEL_REPOSITORY_NOT_FOUND';
    }
}
