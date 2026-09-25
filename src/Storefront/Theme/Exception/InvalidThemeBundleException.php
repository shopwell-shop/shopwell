<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed, use {@see ThemeException::invalidThemeBundle} instead
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class InvalidThemeBundleException extends ShopwellHttpException
{
    public function __construct(string $themeName)
    {
        parent::__construct('Unable to find the theme.json for "{{ themeName }}"', ['themeName' => $themeName]);
    }

    public function getErrorCode(): string
    {
        return 'THEME__INVALID_THEME_BUNDLE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
