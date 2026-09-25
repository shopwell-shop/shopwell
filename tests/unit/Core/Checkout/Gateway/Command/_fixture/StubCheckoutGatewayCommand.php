<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command\_fixture;

use Shopwell\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class StubCheckoutGatewayCommand extends AbstractCheckoutGatewayCommand
{
    public const COMMAND_KEY = 'test';

    /**
     * @param string[] $paymentMethodTechnicalNames
     */
    public function __construct(
        public readonly array $paymentMethodTechnicalNames
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
