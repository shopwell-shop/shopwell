<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopwell\Core\Checkout\Gateway\Command\RemoveShippingMethodCommand;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AbstractCheckoutGatewayCommand::class)]
class AbstractCheckoutGatewayCommandTest extends TestCase
{
    public function testCreateFrom(): void
    {
        $command = RemoveShippingMethodCommand::createFromPayload(['shippingMethodTechnicalName' => 'test']);

        static::assertSame('test', $command->shippingMethodTechnicalName);
    }
}
