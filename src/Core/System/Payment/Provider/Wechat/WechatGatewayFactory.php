<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use Payum\Core\Action\ActionInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentApiInterface;

/**
 * The payum gateway factory of wechat pay - api v3.
 *
 * @internal
 */
#[Package('framework')]
final class WechatGatewayFactory extends AbstractPaymentGatewayFactory
{
    final public const PROVIDER = 'wechat';

    /**
     * The channels of wechat pay as they are declared in `Resources/config/wechat.xml`. They share the
     * merchant account and differ in the way the payer is handed over (and in their appid).
     */
    final public const CHANNEL_H5 = 'wechat_h5';

    final public const CHANNEL_NATIVE = 'wechat_native';

    final public const CHANNEL_JSAPI = 'wechat_jsapi';

    final public const CHANNEL_MINI = 'wechat_mini';

    final public const CHANNEL_APP = 'wechat_app';

    /**
     * The name of the channel parameter that carries the appid of a channel. It belongs to the channel
     * and not to the merchant account, because the appid is bound to the merchant id and differs
     * between the products of wechat - the h5 payment and the jsapi payment use different ones.
     */
    final public const PARAM_APP_ID = 'appid';

    /**
     * The runtime parameter of the jsapi and mini program channels: the openid of the payer.
     */
    final public const RUNTIME_OPEN_ID = 'openid';

    public function provider(): string
    {
        return self::PROVIDER;
    }

    protected function createApi(array $config): PaymentApiInterface
    {
        return new WechatApi($config);
    }

    /**
     * @return list<ActionInterface>
     */
    protected function createActions(): array
    {
        return [
            new WechatCaptureAction(),
            new WechatRefundAction(),
            new WechatNotifyAction(),
        ];
    }
}
