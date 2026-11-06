<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\Cycle\PaymentCycleRules;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;

/**
 * Validated provider declaration with its products, credential schema and protocol actions.
 * Merchant credentials are resolved from an explicit account for each execution.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentProvider
{
    /**
     * @param string $label the **snippet key** of the interface name, not the name itself
     * @param list<PaymentConfigField> $credentials the fields of the merchant account, in the order the administration renders them
     * @param array<string, PaymentProduct> $products the offered products, indexed by their technical name
     * @param PayParamsDecoderInterface|null $payParamsDecoder the interface knows the shape of its own answer; used to normalize a recorded provider answer
     * @param list<PaymentCapability> $capabilities everything the interface can do: the union of what its actions declare and what it declares itself
     * @param PaymentCycleRules|null $cycleRules what a merchant-initiated charge has to follow, null when the interface cannot be charged repeatedly
     * @param PaymentTransferRules|null $transferRules what a payout has to follow, null when the interface cannot pay out at all
     */
    public function __construct(
        public string $technicalName,
        public string $label,
        public array $credentials,
        public array $products,
        public PaymentGatewayFactory $gatewayFactory,
        public PaymentNotificationExtractorInterface $notificationExtractor,
        public ?PayParamsDecoderInterface $payParamsDecoder = null,
        public array $capabilities = [],
        public ?PaymentCycleRules $cycleRules = null,
        public ?PaymentTransferRules $transferRules = null,
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
     * its answer or the answer belongs to a flow only an app can continue - and the persisted attempt must be reconciled.
     *
     * @param array<string, mixed> $response
     */
    public function payParamsFromResponse(array $response): ?PaymentHandover
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

    public function hasProduct(string $technicalName): bool
    {
        return isset($this->products[$technicalName]);
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
