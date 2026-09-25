<?php declare(strict_types=1);

namespace Shopwell\Core\Content\LandingPage;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class LandingPageException extends HttpException
{
    public const EXCEPTION_CODE_LANDING_PAGE_NOT_FOUND = 'CONTENT__LANDING_PAGE_NOT_FOUND';

    public static function notFound(string $id): self
    {
        return new self(
            404,
            self::EXCEPTION_CODE_LANDING_PAGE_NOT_FOUND,
            'Landing page "{{ landingPageId }}" not found.',
            ['landingPageId' => $id]
        );
    }
}
