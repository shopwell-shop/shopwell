<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway\Command\Handler;

use Shopwell\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopwell\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopwell\Core\Checkout\Gateway\Command\RemovePaymentMethodCommand;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class RemovePaymentMethodCommandHandler extends AbstractCheckoutGatewayCommandHandler
{
    public static function supportedCommands(): array
    {
        return [
            RemovePaymentMethodCommand::class,
        ];
    }

    /**
     * @param RemovePaymentMethodCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        $technicalName = $command->paymentMethodTechnicalName;
        $methods = $response->getAvailablePaymentMethods();

        $methods = $methods->filter(static function (PaymentMethodEntity $method) use ($technicalName) {
            return $method->getTechnicalName() !== $technicalName;
        });

        $response->setAvailablePaymentMethods($methods);
    }
}
