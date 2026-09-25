<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CategoryNotFoundException extends ShopwellHttpException
{
    public function __construct(string $categoryId)
    {
        parent::__construct(
            'Category "{{ categoryId }}" not found.',
            ['categoryId' => $categoryId]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__CATEGORY_NOT_FOUND';
    }
}
