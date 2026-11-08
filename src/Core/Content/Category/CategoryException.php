<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category;

use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
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
    final public const INVALID_FIELD_VALUE_TYPE = 'CONTENT__CATEGORY_INVALID_FIELD_VALUE_TYPE';

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

    public static function invalidFieldValueType(string $fieldName, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_FIELD_VALUE_TYPE,
            'Field {{ fieldName }} expected {{ expectedType }}, got {{ actualType }}',
            ['fieldName' => $fieldName, 'expectedType' => $expectedType, 'actualType' => $actualType]
        );
    }
}
