<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo;

use Shopwell\Core\Content\Seo\Exception\InvalidTemplateException;
use Shopwell\Core\Content\Seo\Exception\NoEntitiesForPreviewException;
use Shopwell\Core\Content\Seo\Exception\SeoUrlRouteNotFoundException;
use Shopwell\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

#[Package('inventory')]
class SeoException extends HttpException
{
    public const SALES_CHANNEL_ID_PARAMETER_IS_MISSING = 'FRAMEWORK__SALES_CHANNEL_ID_PARAMETER_IS_MISSING';
    public const TEMPLATE_PARAMETER_IS_MISSING = 'FRAMEWORK__TEMPLATE_PARAMETER_IS_MISSING';
    public const ROUTE_NAME_PARAMETER_IS_MISSING = 'FRAMEWORK__ROUTE_NAME_PARAMETER_IS_MISSING';
    public const ENTITY_NAME_PARAMETER_IS_MISSING = 'FRAMEWORK__ENTITY_NAME_PARAMETER_IS_MISSING';
    public const SALES_CHANNEL_NOT_FOUND = 'FRAMEWORK__SALES_CHANNEL_NOT_FOUND';
    public const SEO_URL_ROUTE_NOT_FOUND = 'CONTENT__SEO_URL_ROUTE_NOT_FOUND';
    /**
     * @internal tag:v6.8.0 - Will be removed once $context is required in event constructors
     */
    public const INVALID_EVENT_DATA = 'CONTENT__SEO__INVALID_EVENT_DATA';

    /**
     * @internal tag:v6.8.0 - Will be removed once $context is required in event constructors
     */
    public static function invalidEventData(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_EVENT_DATA,
            $message
        );
    }

    public static function invalidSalesChannelId(string $salesChannelId): ShopwellHttpException
    {
        return new InvalidSalesChannelIdException($salesChannelId);
    }

    public static function salesChannelIdParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SALES_CHANNEL_ID_PARAMETER_IS_MISSING,
            'Parameter "salesChannelId" is missing.',
        );
    }

    public static function templateParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TEMPLATE_PARAMETER_IS_MISSING,
            'Parameter "template" is missing.',
        );
    }

    public static function entityNameParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ENTITY_NAME_PARAMETER_IS_MISSING,
            'Parameter "entityName" is missing.',
        );
    }

    public static function routeNameParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ROUTE_NAME_PARAMETER_IS_MISSING,
            'Parameter "routeName" is missing.',
        );
    }

    public static function salesChannelNotFound(string $salesChannelId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SALES_CHANNEL_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'sales channel', 'field' => 'id', 'value' => $salesChannelId]
        );
    }

    public static function seoUrlRouteNotFound(string $routeName): ShopwellHttpException
    {
        return new SeoUrlRouteNotFoundException($routeName);
    }

    public static function noEntitiesForPreview(string $entityName, string $routeName): ShopwellHttpException
    {
        return new NoEntitiesForPreviewException($entityName, $routeName);
    }

    public static function invalidTemplate(string $message): ShopwellHttpException
    {
        return new InvalidTemplateException($message);
    }

    public static function unexpectedType(mixed $givenType, string $expectedType): UnexpectedTypeException
    {
        return new UnexpectedTypeException($givenType, $expectedType);
    }
}
