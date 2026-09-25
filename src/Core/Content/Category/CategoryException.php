<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category;

use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Content\Cms\Exception\PageNotFoundException;
use Shopwell\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class CategoryException extends HttpException
{
    public const SERVICE_CATEGORY_NOT_FOUND = 'CHECKOUT__SERVICE_CATEGORY_NOT_FOUND';
    public const FOOTER_CATEGORY_NOT_FOUND = 'CHECKOUT__FOOTER_CATEGORY_NOT_FOUND';
    public const AFTER_CATEGORY_NOT_FOUND = 'CONTENT__AFTER_CATEGORY_NOT_FOUND';
    public const CMS_PAGE_NOT_FOUND = 'CONTENT__CMS_PAGE_NOT_FOUND';

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function pageNotFound(string $pageId): ShopwellHttpException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new PageNotFoundException($pageId);
        }

        return new self(
            Response::HTTP_NOT_FOUND,
            self::CMS_PAGE_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'page', 'field' => 'ID', 'value' => $pageId]
        );
    }

    public static function categoryNotFound(string $id): ShopwellHttpException
    {
        return new CategoryNotFoundException($id);
    }

    public static function serviceCategoryNotFoundForSalesChannel(string $salesChannelName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_CATEGORY_NOT_FOUND,
            'Service category, for sales channel {{ salesChannelName }}, is not set',
            ['salesChannelName' => $salesChannelName]
        );
    }

    public static function footerCategoryNotFoundForSalesChannel(string $salesChannelName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FOOTER_CATEGORY_NOT_FOUND,
            'Footer category, for sales channel {{ salesChannelName }}, is not set',
            ['salesChannelName' => $salesChannelName]
        );
    }

    public static function afterCategoryNotFound(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::AFTER_CATEGORY_NOT_FOUND,
            'Category to insert after not found.',
        );
    }
}
