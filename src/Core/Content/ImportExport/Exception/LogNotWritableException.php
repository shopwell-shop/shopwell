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
class LogNotWritableException extends ShopwellHttpException
{
    /**
     * @param list<string> $ids
     */
    public function __construct(array $ids)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct('Entity import_export_log is write-protected if not in system scope', ['ids' => $ids]);
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return 'CONTENT__IMPORT_EXPORT_LOG_NOT_WRITABLE';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return Response::HTTP_BAD_REQUEST;
    }
}
