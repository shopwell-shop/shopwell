<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Payum\Core\Action\ActionInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentApiInterface;

/**
 * The payum gateway factory of alipay.
 *
 * There is one factory per payment interface and not per channel: the channels of alipay share the
 * credentials, the api and everything but the call that opens a payment, so the capture action
 * dispatches on the channel itself.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayGatewayFactory extends AbstractPaymentGatewayFactory
{
    final public const PROVIDER = 'alipay';

    /**
     * The channels of alipay as they are declared in `Resources/config/alipay.xml`. The names are the
     * contract between the declaration and the capture action, which is the only place a channel
     * matters - adding a channel means declaring it there and teaching the action to open it.
     */
    final public const CHANNEL_H5 = 'alipay_h5';

    final public const CHANNEL_WEB = 'alipay_web';

    final public const CHANNEL_QR = 'alipay_qr';

    final public const CHANNEL_APP = 'alipay_app';

    final public const CHANNEL_MINI = 'alipay_mini';

    public function provider(): string
    {
        return self::PROVIDER;
    }

    protected function createApi(array $config): PaymentApiInterface
    {
        return new AlipayApi($config);
    }

    /**
     * @return list<ActionInterface>
     */
    protected function createActions(): array
    {
        return [
            new AlipayCaptureAction(),
            new AlipayRefundAction(),
            new AlipayNotifyAction(),
        ];
    }
}
