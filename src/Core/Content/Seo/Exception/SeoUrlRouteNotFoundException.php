<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo\Exception;

use Shopwell\Core\Content\Seo\SeoException;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class SeoUrlRouteNotFoundException extends SeoException
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    final public const ERROR_CODE = 'FRAMEWORK__SEO_URL_ROUTE_NOT_FOUND';

    public function __construct(string $routeName)
    {
        $errorCode = self::SEO_URL_ROUTE_NOT_FOUND;
        if (!Feature::isActive('v6.8.0.0')) {
            $errorCode = self::ERROR_CODE;
        }

        parent::__construct(
            Response::HTTP_NOT_FOUND,
            $errorCode,
            self::$couldNotFindMessage,
            ['entity' => 'SEO URL route', 'field' => 'name', 'value' => $routeName]
        );
    }
}
