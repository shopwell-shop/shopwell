<?php
declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Media\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Shopwell\Storefront\Framework\StorefrontFrameworkException;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed, use {@see StorefrontFrameworkException::mediaValidatorMissing} instead
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaValidatorMissingException extends ShopwellHttpException
{
    public function __construct(string $type)
    {
        parent::__construct('No validator for {{ type }} was found.', ['type' => $type]);
    }

    public function getErrorCode(): string
    {
        return 'STOREFRONT__MEDIA_VALIDATOR_MISSING';
    }
}
