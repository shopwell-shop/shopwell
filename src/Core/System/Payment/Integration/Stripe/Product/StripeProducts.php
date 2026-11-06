<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Stripe\Product;

use Shopwell\Core\Framework\Log\Package;

/**
 * Built-in product identifiers. Plugins may register additional identifiers.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class StripeProducts
{
    public const CHECKOUT = 'stripe_checkout';
}
