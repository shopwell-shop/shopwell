<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed, use FlowException::customTriggerByNameNotFound() instead
 */
#[Package('after-sales')]
class CustomTriggerByNameNotFoundException extends ShopwellHttpException
{
    public function __construct(string $eventName)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'The provided event name {{ eventName }} is invalid or uninstalled and no custom trigger could be found.',
            ['eventName' => $eventName]
        );
    }

    public function getErrorCode(): string
    {
        return 'ADMINISTRATION__CUSTOM_TRIGGER_BY_NAME_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
