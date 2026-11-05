<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Alipay\Product;

use Shopwell\Core\Framework\Log\Package;

/**
 * Built-in product identifiers. Plugins may register additional identifiers.
 */
#[Package('framework')]
final class AlipayProducts
{
    public const H5 = 'alipay_h5';
    public const WEB = 'alipay_web';
    public const QR = 'alipay_qr';
    public const APP = 'alipay_app';
    public const MINI = 'alipay_mini';
}
