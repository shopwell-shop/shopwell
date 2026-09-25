<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed as it is not used anymore
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class SalesChannelNotFoundException extends ShopwellHttpException
{
    public function __construct(string $salesChannelId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        parent::__construct(
            'Sales channel with id "{{ salesChannelId }}" was not found.',
            ['salesChannelId' => $salesChannelId]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return 'CONTENT__SALES_CHANNEL_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return Response::HTTP_BAD_REQUEST;
    }
}
