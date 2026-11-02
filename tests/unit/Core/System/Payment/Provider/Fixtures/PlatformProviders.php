<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Psr\Log\NullLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayCaptureAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayCloseAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayNotifyAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayQueryAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayRefundAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayTransferAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayTransferQueryAction;
use Shopwell\Core\System\Payment\Integration\Alipay\AlipayProvider;
use Shopwell\Core\System\Payment\Integration\Alipay\Notification\AlipayNotificationExtractor;
use Shopwell\Core\System\Payment\Integration\Alipay\Notification\AlipayTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Integration\Alipay\Request\AlipayPayParamsDecoder;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalCaptureAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalNotifyAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalQueryAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalRefundAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalTransferAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalTransferQueryAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Notification\PaypalNotificationExtractor;
use Shopwell\Core\System\Payment\Integration\Paypal\Notification\PaypalTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Integration\Paypal\PaypalProvider;
use Shopwell\Core\System\Payment\Integration\Stripe\Action\StripeCaptureAction;
use Shopwell\Core\System\Payment\Integration\Stripe\Action\StripeNotifyAction;
use Shopwell\Core\System\Payment\Integration\Stripe\Action\StripeQueryAction;
use Shopwell\Core\System\Payment\Integration\Stripe\Action\StripeRefundAction;
use Shopwell\Core\System\Payment\Integration\Stripe\Action\StripeTransferAction;
use Shopwell\Core\System\Payment\Integration\Stripe\Action\StripeTransferQueryAction;
use Shopwell\Core\System\Payment\Integration\Stripe\Notification\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\Integration\Stripe\Notification\StripeTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Integration\Stripe\StripeProvider;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatCaptureAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatCloseAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatNotifyAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatQueryAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatRefundAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatTransferAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatTransferQueryAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Notification\WechatNotificationExtractor;
use Shopwell\Core\System\Payment\Integration\Wechat\Notification\WechatTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Integration\Wechat\Request\WechatPayParamsDecoder;
use Shopwell\Core\System\Payment\Integration\Wechat\WechatProvider;
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
