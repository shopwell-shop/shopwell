<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Psr\Log\NullLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayCaptureAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayCloseAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayNotifyAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayPayParamsDecoder;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayProvider;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayQueryAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayRefundAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayTransferAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayTransferQueryAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalCaptureAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalNotifyAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalProvider;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalQueryAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalRefundAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalTransferAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalTransferQueryAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeCaptureAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotifyAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeProvider;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeQueryAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeRefundAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeTransferAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeTransferQueryAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatCaptureAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatCloseAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatNotifyAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatPayParamsDecoder;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatProvider;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatQueryAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatRefundAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatTransferAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatTransferQueryAction;
use Symfony\Component\HttpClient\NativeHttpClient;

/**
 * Builds the four declarations the platform ships with their real actions.
 *
 * The declarations are final and take their actions as constructor arguments - the container normally
 * provides them. A test that wants to look at what an interface declares has to assemble that graph
 * itself, and doing it once here keeps the four declaration tests readable.
 *
 * @internal
 */
#[Package('framework')]
final class PlatformProviders
{
    /**
     * The secret the cipher is used with. The cipher is only constructed here, never asked to encrypt.
     */
    final public const CIPHER_SECRET = 'unit-test-secret';

    public static function alipay(): AlipayProvider
    {
        return new AlipayProvider(
            new AlipayCaptureAction(),
            new AlipayRefundAction(),
            new AlipayNotifyAction(),
            new AlipayQueryAction(),
            new AlipayCloseAction(),
            new AlipayTransferAction(self::cipher()),
            new AlipayTransferQueryAction(),
            new AlipayNotificationExtractor(),
            new AlipayPayParamsDecoder(),
            new AlipayTransferNotificationVerifier(),
        );
    }

    public static function wechat(): WechatProvider
    {
        return new WechatProvider(
            new WechatCaptureAction(),
            new WechatRefundAction(),
            new WechatNotifyAction(),
            new WechatQueryAction(),
            new WechatCloseAction(),
            new WechatTransferAction(self::cipher()),
            new WechatTransferQueryAction(),
            new WechatNotificationExtractor(),
            new WechatPayParamsDecoder(),
            new WechatTransferNotificationVerifier(new NullLogger()),
        );
    }

    public static function paypal(): PaypalProvider
    {
        return new PaypalProvider(
            new PaypalCaptureAction(),
            new PaypalRefundAction(),
            new PaypalNotifyAction(),
            new PaypalQueryAction(),
            new PaypalTransferAction(self::cipher()),
            new PaypalTransferQueryAction(),
            new PaypalNotificationExtractor(),
            new PaypalTransferNotificationVerifier(),
            new NativeHttpClient(),
        );
    }

    public static function stripe(): StripeProvider
    {
        return new StripeProvider(
            new StripeCaptureAction(),
            new StripeRefundAction(),
            new StripeNotifyAction(),
            new StripeQueryAction(),
            new StripeTransferAction(),
            new StripeTransferQueryAction(),
            new StripeNotificationExtractor(),
            new StripeTransferNotificationVerifier(),
        );
    }

    private static function cipher(): PaymentCredentialCipher
    {
        return new PaymentCredentialCipher(self::CIPHER_SECRET);
    }
}
