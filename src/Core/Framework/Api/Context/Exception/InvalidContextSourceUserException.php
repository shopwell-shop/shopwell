<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Context\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @deprecated tag:v6.8.0 - Will be removed in v6.8.0.0. Use `\Shopwell\Core\Framework\Store\StoreException::invalidContextSourceUser` instead.
 */
#[Package('framework')]
class InvalidContextSourceUserException extends ShopwellHttpException
{
    public function __construct(string $contextSource)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            '{{ contextSource }} does not have a valid user ID',
            ['contextSource' => $contextSource]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CONTEXT_SOURCE_USER';
    }
}
