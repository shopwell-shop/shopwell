<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentProductEligibility;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProductEligibility::class)]
class PaymentProductEligibilityTest extends TestCase
{
    public function testCurrencyDeviceAndHandoverCapabilitiesMustAllMatch(): void
    {
        $channel = new PaymentProduct('plugin_web', 'plugin', 'Web', [PaymentInteraction::REDIRECT], 'plugin', ['CNY'], [new PaymentScene('browser', 'web', 'browser')]);
        $eligibility = new PaymentProductEligibility();
        static::assertTrue($eligibility->isEligible($channel, new PaymentContext('CNY', new PaymentScene('browser', 'web', 'browser'))));
        static::assertFalse($eligibility->isEligible($channel, new PaymentContext('USD', new PaymentScene('browser', 'web', 'browser'))));
        static::assertFalse($eligibility->isEligible($channel, new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'browser'))));
        static::assertFalse($eligibility->isEligible($channel, new PaymentContext('CNY', new PaymentScene('browser', 'web', 'browser'), supportedInteractions: [PaymentInteraction::QRCODE])));
    }

    public function testSdkRequiresTheConcreteProductCapabilityAndScopedIdentity(): void
    {
        $channel = new PaymentProduct('wechat_jsapi', 'wechat', 'WeChat', [PaymentInteraction::SDK], 'wechat', ['CNY'], [new PaymentScene('browser', 'wap', 'browser'), new PaymentScene('browser', 'wap', 'wechat'), new PaymentScene('browser', 'wap', 'alipay')], runtimeParams: ['openid' => true]);
        $eligibility = new PaymentProductEligibility();
        static::assertFalse($eligibility->isEligible($channel, new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'wechat'), [PaymentInteraction::SDK], ['alipay_app'], [], ['merchant-account' => ['wechat_jsapi' => ['openid' => 'payer']]])));
        static::assertFalse($eligibility->isEligible($channel, new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'wechat'), [PaymentInteraction::SDK], ['wechat_jsapi'])));
        static::assertFalse($eligibility->isEligible($channel, new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'wechat'), [PaymentInteraction::SDK], ['wechat_jsapi'], [], ['merchant-account' => ['wechat_mini' => ['openid' => 'payer']]])));
        static::assertTrue($eligibility->isEligible($channel, new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'wechat'), [PaymentInteraction::SDK], ['wechat_jsapi'], [], ['merchant-account' => ['wechat_jsapi' => ['openid' => 'payer']]])));
    }
}
