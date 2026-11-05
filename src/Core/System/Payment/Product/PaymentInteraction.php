<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Product;

use Shopwell\Core\Framework\Log\Package;

/**
 * Built-in handover identifiers. Products and clients may agree on additional interactions.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class PaymentInteraction
{
    public const REDIRECT = 'redirect';
    public const QRCODE = 'qrcode';
    public const SDK = 'sdk';
}
