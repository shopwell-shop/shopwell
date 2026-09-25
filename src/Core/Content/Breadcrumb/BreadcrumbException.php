<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Breadcrumb;

use Shopwell\Core\Content\Category\CategoryException;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Content\Product\Exception\ProductNotFoundException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class BreadcrumbException extends CategoryException
{
    public const BREADCRUMB_CATEGORY_NOT_FOUND = 'BREADCRUMB_CATEGORY_NOT_FOUND';

    public static function categoryNotFoundForProduct(string $productId): self
    {
        return new self(
            Response::HTTP_NO_CONTENT,
            self::BREADCRUMB_CATEGORY_NOT_FOUND,
            'The main category for product {{ productId }} is not found',
            ['productId' => $productId]
        );
    }

    public static function categoryNotFound(string $id): ShopwellHttpException
    {
        return new CategoryNotFoundException($id);
    }

    public static function productNotFound(string $id): ShopwellHttpException
    {
        return new ProductNotFoundException($id);
    }
}
