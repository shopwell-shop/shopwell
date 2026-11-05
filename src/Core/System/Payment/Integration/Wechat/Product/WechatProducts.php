<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Wechat\Product;

use Shopwell\Core\Framework\Log\Package;

/**
 * Built-in product identifiers. Plugins may register additional identifiers.
 */
#[Package('framework')]
final class WechatProducts
{
    public const H5 = 'wechat_h5';
    public const NATIVE = 'wechat_native';
    public const JSAPI = 'wechat_jsapi';
    public const MINI = 'wechat_mini';
    public const APP = 'wechat_app';
}
