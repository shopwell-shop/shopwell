<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Payum\Core\GatewayFactoryInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Channel\PaymentChannel;

/**
 * One payment interface the platform offers to the apps: alipay, wechat pay, paypal or stripe.
 *
 * An interface is the merchant account and therefore the only place credentials live: every channel it
 * offers is charged on the very same account. That is what makes "use the platform account or your own"
 * a decision per interface instead of per product, and it is why adding another product of alipay does
 * not duplicate a single key.
 *
 * An interface is data plus its payum gateway factory and its notification extractor: adding another
 * interface is one more service declaration (or one in an extension), never a new class in the
 * administration.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentProvider
{
    /**
     * @param list<PaymentConfigField> $credentials the fields of the merchant account, in the order the administration renders them
     * @param array<string, PaymentChannel> $channels the offered channels, indexed by their technical name
     */
    public function __construct(
        public string $technicalName,
        public string $label,
        public array $credentials,
        public array $channels,
        public GatewayFactoryInterface $gatewayFactory,
        public PaymentNotificationExtractorInterface $notificationExtractor,
    ) {
    }

    /**
     * Binds the services to the declaration of the interface.
     */
    public static function fromDefinition(
        PaymentProviderDefinition $definition,
        GatewayFactoryInterface $gatewayFactory,
        PaymentNotificationExtractorInterface $notificationExtractor
    ): self {
        $channels = [];
        foreach ($definition->channels as $channel) {
            $channels[$channel->technicalName] = $channel;
        }

        return new self(
            technicalName: $definition->technicalName,
            label: $definition->label,
            credentials: $definition->credentials,
            channels: $channels,
            gatewayFactory: $gatewayFactory,
            notificationExtractor: $notificationExtractor,
        );
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
