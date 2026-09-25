<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway\Command;

use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class AddPaymentMethodExtensionCommand extends AbstractCheckoutGatewayCommand
{
    public const COMMAND_KEY = 'add-payment-method-extension';

    /**
     * @param array<array-key, mixed> $extensionsPayload
     */
    public function __construct(
        public readonly string $paymentMethodTechnicalName,
        public readonly string $extensionKey,
        public readonly array $extensionsPayload,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
