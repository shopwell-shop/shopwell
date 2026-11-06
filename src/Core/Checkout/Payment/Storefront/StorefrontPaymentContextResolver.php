<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Storefront;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
class StorefrontPaymentContextResolver
{
    public function resolve(Request $request, string $currency): PaymentContext
    {
        $agent = strtolower($request->headers->get('User-Agent', ''));
        $type = match (true) {
            str_contains($agent, 'micromessenger') => new PaymentScene('browser', 'wap', 'wechat'),
            str_contains($agent, 'alipayclient') => new PaymentScene('browser', 'wap', 'alipay'),
            preg_match('/android|iphone|ipad|ipod|mobile/', $agent) === 1 => new PaymentScene('browser', 'wap', 'browser'),
            default => new PaymentScene('browser', 'web', 'browser'),
        };

        // A browser request proves neither a native SDK nor an appid-scoped payer identity.
        return new PaymentContext(
            currency: $currency,
            scene: $type,
        );
    }
}
