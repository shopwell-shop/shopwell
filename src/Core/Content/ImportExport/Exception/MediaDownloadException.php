<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class MediaDownloadException extends ShopwellHttpException
{
    public function __construct(?string $url)
    {
        parent::__construct('Cannot download media from url: {{ url }}', ['url' => $url ?? 'null']);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_MEDIA_DOWNLOAD_FAILED';
    }
}
