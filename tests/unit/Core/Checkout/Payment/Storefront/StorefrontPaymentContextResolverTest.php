<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentContextResolver;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StorefrontPaymentContextResolver::class)]
class StorefrontPaymentContextResolverTest extends TestCase
{
    #[DataProvider('browsers')]
    public function testBrowserSceneIsDerivedOnTheServer(string $agent, string $device, PaymentScene $scene): void
    {
        $request = Request::create('/', 'POST', ['device' => 'mini', 'openid' => 'forged', 'supportedInteractions' => ['sdk']], server: ['HTTP_USER_AGENT' => $agent]);
        $context = (new StorefrontPaymentContextResolver())->resolve($request, 'CNY');

        static::assertSame($device, $context->device);
        static::assertEquals($scene, $context->scene);
        static::assertSame([PaymentInteraction::REDIRECT, PaymentInteraction::QRCODE], $context->supportedInteractions);
        static::assertSame([], $context->runtimeParams);
    }

    public static function browsers(): iterable
    {
        yield 'unknown browser defaults to desktop' => ['', 'web', new PaymentScene('browser', 'web', 'browser')];
        yield 'desktop browser' => ['Mozilla/5.0 (X11; Linux x86_64)', 'web', new PaymentScene('browser', 'web', 'browser')];
        yield 'ordinary mobile browser' => ['Mozilla/5.0 iPhone Mobile Safari', 'wap', new PaymentScene('browser', 'wap', 'browser')];
        yield 'Alipay browser' => ['AlipayClient/10.0', 'wap', new PaymentScene('browser', 'wap', 'alipay')];
        yield 'wechat browser' => ['Mozilla/5.0 Android MicroMessenger', 'wap', new PaymentScene('browser', 'wap', 'wechat')];
    }
}
