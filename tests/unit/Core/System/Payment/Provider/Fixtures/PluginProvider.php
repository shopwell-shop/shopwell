<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;

/**
 * A payment interface of an extension, written the way an extension writes it.
 *
 * The point of this fixture is its constructor: it takes no arguments at all. An extension that adds a
 * payment interface declares one service, one tag and nothing else - it does not register actions, does
 * not assemble a gateway factory and does not know that a core compiler pass exists. That is the whole
 * claim of the declaration mechanism, and this class is what a test of that claim hands to the
 * container.
 *
 * @internal
 */
#[Package('framework')]
final class PluginProvider extends AbstractPaymentProvider
{
    final public const TECHNICAL_NAME = 'pluginpay';

    final public const CHANNEL = 'pluginpay_qr';

    final public const CREDENTIAL = 'api_key';

    public function getName(): string
    {
        return self::TECHNICAL_NAME;
    }

    public function getLabel(): string
    {
        return 'sw-settings-payment-provider.provider.pluginpay';
    }

    public function getApiClass(): string
    {
        return TestPaymentApi::class;
    }

    /**
     * @return list<AbstractPaymentAction>
     */
    public function getActions(): array
    {
        return [new TestPaymentAction(PaymentCapability::CAPTURE)];
    }

    public function getCaptureAction(): AbstractPaymentAction
    {
        return $this->getActions()[0];
    }

    public function getNotificationExtractor(): PaymentNotificationExtractorInterface
    {
        return new TestNotificationExtractor();
    }

    /**
     * @return list<PaymentConfigField>
     */
    public function getCredentials(): array
    {
        return [
            new PaymentConfigField(
                name: self::CREDENTIAL,
                label: 'sw-settings-payment-provider.provider.pluginpay.apiKey',
                type: PaymentConfigField::TYPE_PASSWORD,
                required: true,
            ),
        ];
    }

    /**
     * @return list<PaymentChannel>
     */
    public function getChannels(): array
    {
        return [
            new PaymentChannel(
                technicalName: self::CHANNEL,
                providerTechnicalName: self::TECHNICAL_NAME,
                label: 'sw-settings-payment-provider.channel.pluginpay_qr',
                flow: PaymentFlow::QRCODE,
            ),
        ];
    }
}
