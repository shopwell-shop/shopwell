<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command\_fixture;

use Shopwell\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopwell\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopwell\Core\Checkout\Gateway\Command\Handler\AbstractCheckoutGatewayCommandHandler;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class StubCheckoutGatewayHandler extends AbstractCheckoutGatewayCommandHandler
{
    public static function supportedCommands(): array
    {
        return [StubCheckoutGatewayCommand::class, StubCheckoutGatewayFooCommand::class];
    }

    /**
     * @param StubCheckoutGatewayCommand|StubCheckoutGatewayFooCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        if ($command instanceof StubCheckoutGatewayFooCommand) {
            return;
        }

        $paymentMethods = new PaymentMethodCollection();

        foreach ($command->paymentMethodTechnicalNames as $paymentMethodTechnicalName) {
            $paymentMethod = new PaymentMethodEntity();
            $paymentMethod->setId(Uuid::randomHex());
            $paymentMethod->setTechnicalName($paymentMethodTechnicalName);

            $paymentMethods->add($paymentMethod);
        }

        $response->setAvailablePaymentMethods($paymentMethods);
    }
}
