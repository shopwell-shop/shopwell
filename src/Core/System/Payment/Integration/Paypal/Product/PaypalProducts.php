<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Paypal\Product;

use Shopwell\Core\Framework\Log\Package;

/**
 * Built-in product identifiers. Plugins may register additional identifiers.
 */
#[Package('framework')]
final class PaypalProducts
{
    public const CHECKOUT = 'paypal_checkout';
}
