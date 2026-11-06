<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Storefront\Settlement;

use Shopwell\Core\Framework\Log\Package;

/**
 * The marker on a payment order that tells which purchase it collects.
 *
 * The gateway is shared by everything the shop sells, so a paid payment order has to say what it was for
 * before anything may be credited or activated. Building the marker and reading it back used to be two
 * literals and a `substr()` in four places; a marker that no longer round-trips credits nothing, silently.
 *
 * @final
 */
#[Package('checkout')]
class PurchaseAttach
{
    public function __construct(private readonly string $prefix)
    {
    }

    public function forPurchase(string $purchaseId): string
    {
        return $this->prefix . $purchaseId;
    }

    /**
     * The purchase a payment order was created for, or null when it belongs to something else.
     */
    public function purchaseIdOf(?string $attach): ?string
    {
        if ($attach === null || !str_starts_with($attach, $this->prefix)) {
            return null;
        }

        $purchaseId = substr($attach, \strlen($this->prefix));

        return $purchaseId === '' ? null : $purchaseId;
    }
}
