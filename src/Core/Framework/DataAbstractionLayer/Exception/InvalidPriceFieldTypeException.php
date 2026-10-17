<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Exception;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use CartException::invalidPriceFieldTypeException() or DataAbstractionLayerException::invalidPriceFieldType() instead
 */
#[Package('framework')]
class InvalidPriceFieldTypeException extends ShopwellHttpException
{
    public function __construct(string $type)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'The price field does not contain a valid "type" value. Received {{ type }} ',
            ['type' => $type]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_PRICE_FIELD_TYPE';
    }
}
