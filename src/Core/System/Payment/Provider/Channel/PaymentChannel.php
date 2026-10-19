<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Channel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;

/**
 * One way of paying a payment interface offers, e.g. the alipay h5 payment or the wechat native
 * (scan a qr code) payment.
 *
 * A channel carries no credentials: the merchant account belongs to the payment interface, and every
 * channel of that interface is charged on the very same account. What a channel decides is how the
 * payer is handed over - and it is the *only* step where the channel matters: querying, closing,
 * refunding and verifying a notification all work on the credentials of the interface alone, which is
 * why the adapters talk to an interface api and only dispatch on the channel when a payment is opened.
 *
 * A channel is data plus its declaration in the `payment-provider.xml` of its interface: the flow, the
 * currencies, the devices and the runtime parameters are declared there, so the administration and the
 * api pick up a new channel without a code change. The call that actually opens the payment is the one
 * thing that stays with the interface - its products differ in the api they call - and is named per
 * channel by the capture action of that interface.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentChannel
{
    /**
     * The devices a channel can be used on.
     */
    final public const DEVICE_WAP = 'wap';

    final public const DEVICE_WEB = 'web';

    final public const DEVICE_APP = 'app';

    final public const DEVICE_MINI = 'mini';

    /**
     * @param string $technicalName the name the api and the payment orders work with, e.g. `alipay_h5`
     * @param string $providerTechnicalName the payment interface that offers this channel and holds the credentials
     * @param list<string> $currencies the iso 4217 codes the channel can process, empty means every currency
     * @param list<string> $devices the devices the channel is usable on, empty means every device
     * @param list<PaymentConfigField> $params the parameters of the channel, e.g. the appid of a wechat channel
     * @param array<string, bool> $runtimeParams the parameters a payment call has to bring, name => required
     */
    public function __construct(
        public string $technicalName,
        public string $providerTechnicalName,
        public string $label,
        public PaymentFlow $flow,
        public array $currencies = [],
        public array $devices = [],
        public array $params = [],
        public array $runtimeParams = [],
    ) {
    }

    public function supportsCurrency(string $currency): bool
    {
        return $this->currencies === [] || \in_array(strtoupper($currency), $this->currencies, true);
    }

    /**
     * A channel that declares no device is usable everywhere. A payment that brings no device at all
     * passes as well: the app may simply not know what its payer uses.
     */
    public function supportsDevice(?string $device): bool
    {
        if ($this->devices === [] || $device === null || $device === '') {
            return true;
        }

        return \in_array(strtolower($device), $this->devices, true);
    }

    /**
     * @return list<string>
     */
    public function requiredRuntimeParams(): array
    {
        $required = [];

        foreach ($this->runtimeParams as $name => $isRequired) {
            if ($isRequired) {
                $required[] = $name;
            }
        }

        return $required;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws PaymentException
     */
    public function validateRuntimeParams(array $params): void
    {
        $missing = [];

        foreach ($this->requiredRuntimeParams() as $name) {
            $value = $params[$name] ?? null;

            if (!\is_scalar($value) || trim((string) $value) === '') {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            throw PaymentException::missingRuntimeParams($this->technicalName, $missing);
        }
    }

    /**
     * The declaration as the administration and the api consume it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'channel' => $this->technicalName,
            'provider' => $this->providerTechnicalName,
            'label' => $this->label,
            'flow' => $this->flow->value,
            'currencies' => $this->currencies,
            'devices' => $this->devices,
            'params' => array_map(
                static fn (PaymentConfigField $field): array => $field->toArray(),
                $this->params
            ),
            'runtime_params' => $this->runtimeParams,
        ];
    }
}
