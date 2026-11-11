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
use Shopwell\Core\System\Payment\Method\PaymentMethod;
use Shopwell\Core\System\Payment\Method\PaymentMethodRegistry;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigReader;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Builds the two declarations the platform ships with their real actions.
 *
 * The declarations are final and take their actions as constructor arguments - the container normally
 * provides them. A test that wants to look at what an interface declares has to assemble that graph
 * itself, and doing it once here keeps the two declaration tests readable.
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

    public static function factory(): PaymentProviderFactory
    {
        $methods = new PaymentMethodRegistry(array_map(
            static fn (string $name): PaymentMethod => new PaymentMethod($name, 'payment.method.' . $name),
            ['alipay', 'wechat', 'testpay'],
        ));

        return new PaymentProviderFactory(new PaymentConfigReader(), $methods, new EventDispatcher());
    }

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

    private static function cipher(): PaymentCredentialCipher
    {
        return new PaymentCredentialCipher(self::CIPHER_SECRET);
    }
}
