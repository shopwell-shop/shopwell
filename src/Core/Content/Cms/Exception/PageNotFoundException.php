<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\Exception;

use Shopwell\Core\Content\Cms\CmsException;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use {@see CmsException::pageNotFound} instead
 */
#[Package('discovery')]
class PageNotFoundException extends ShopwellHttpException
{
    final public const ERROR_CODE = 'CONTENT__CMS_PAGE_NOT_FOUND';

    public function __construct(string $pageId)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'Page with id "{{ pageId }}" was not found.',
            ['pageId' => $pageId]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return self::ERROR_CODE;
    }
}
