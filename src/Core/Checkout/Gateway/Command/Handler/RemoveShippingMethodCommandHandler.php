<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway\Command\Handler;

use Shopwell\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopwell\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopwell\Core\Checkout\Gateway\Command\RemoveShippingMethodCommand;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class RemoveShippingMethodCommandHandler extends AbstractCheckoutGatewayCommandHandler
{
    public static function supportedCommands(): array
    {
        return [
            RemoveShippingMethodCommand::class,
        ];
    }

    /**
     * @param RemoveShippingMethodCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        $technicalName = $command->shippingMethodTechnicalName;
        $methods = $response->getAvailableShippingMethods();

        $methods = $methods->filter(static function (ShippingMethodEntity $method) use ($technicalName) {
            return $method->getTechnicalName() !== $technicalName;
        });

        $response->setAvailableShippingMethods($methods);
    }
}
