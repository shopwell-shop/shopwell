<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Unused exception will be removed
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class MappingException extends ShopwellHttpException
{
    public function __construct()
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return 'CONTENT__IMPORT_EXPORT_MAPPING_EXCEPTION';
    }
}
