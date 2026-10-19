<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;

/**
 * One payment interface the platform offers to the apps: alipay, wechat pay, paypal or stripe.
 *
 * An interface is the merchant account and therefore the only place credentials live: every channel it
 * offers is charged on the very same account. That is what makes "use the platform account or your own"
 * a decision per interface instead of per product, and it is why adding another product of alipay does
 * not duplicate a single key.
 *
 * This is the immutable snapshot the rest of the platform works with. It is built once per interface by
 * {@see PaymentProviderFactory} out of the {@see AbstractPaymentProvider} a plugin - or the core -
 * declares, and it is never constructed by a plugin itself: the declaration is the public contract, and
 * everything below is an implementation detail of the platform.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentProvider
{
    /**
     * @param string $label the **snippet key** of the interface name, not the name itself
     * @param list<PaymentConfigField> $credentials the fields of the merchant account, in the order the administration renders them
     * @param array<string, PaymentChannel> $channels the offered channels, indexed by their technical name
     * @param PayParamsDecoderInterface|null $payParamsDecoder the interface knows the shape of its own answer; without a decoder a reopen captures again
     * @param list<PaymentCapability> $capabilities everything the interface can do: the union of what its actions declare and what it declares itself
     */
    public function __construct(
        public string $technicalName,
        public string $label,
        public array $credentials,
        public array $channels,
        public PaymentGatewayFactory $gatewayFactory,
        public PaymentNotificationExtractorInterface $notificationExtractor,
        public ?PayParamsDecoderInterface $payParamsDecoder = null,
        public array $capabilities = [],
    ) {
    }

    /**
     * Whether the interface can do this.
     *
     * Every caller that is about to send a request of its own asks this first: an interface that has no
     * close action answers `false` here instead of failing with a 502 that reads like a broken channel.
     */
    public function supports(PaymentCapability $capability): bool
    {
        return \in_array($capability, $this->capabilities, true);
    }

    /**
     * The parameters a payer who comes back is handed to, rebuilt from the answer of the last capture.
     *
     * Null means the interface cannot hand the payer over again - either it did not record enough of
     * its answer or the answer belongs to a flow only an app can continue - and the payment has to be
     * captured anew.
     *
     * @param array<string, mixed> $response
     */
    public function payParamsFromResponse(array $response): ?PayParams
    {
        return $this->payParamsDecoder?->payParamsFromResponse($response);
    }

    public function credentialField(string $name): ?PaymentConfigField
    {
        foreach ($this->credentials as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    public function hasChannel(string $technicalName): bool
    {
        return isset($this->channels[$technicalName]);
    }

    /**
     * @return list<string>
     */
    public function credentialNames(): array
    {
        return array_map(
            static fn (PaymentConfigField $field): string => $field->name,
            $this->credentials
        );
    }
}
