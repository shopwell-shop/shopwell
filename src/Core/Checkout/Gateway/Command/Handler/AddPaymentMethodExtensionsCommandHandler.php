<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway\Command\Handler;

use Shopwell\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopwell\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopwell\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopwell\Core\Checkout\Gateway\Command\AddPaymentMethodExtensionCommand;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Log\ExceptionLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class AddPaymentMethodExtensionsCommandHandler extends AbstractCheckoutGatewayCommandHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ExceptionLogger $logger,
    ) {
    }

    public static function supportedCommands(): array
    {
        return [
            AddPaymentMethodExtensionCommand::class,
        ];
    }

    /**
     * @param AddPaymentMethodExtensionCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        $method = $response->getAvailablePaymentMethods()->filter(static function (PaymentMethodEntity $method) use ($command) {
            return $method->getTechnicalName() === $command->paymentMethodTechnicalName;
        })->first();

        if (!$method) {
            $this->logger->logOrThrowException(
                CheckoutGatewayException::handlerException('Payment method "{{ technicalName }}" not found', ['technicalName' => $command->paymentMethodTechnicalName])
            );

            return;
        }

        $method->addExtensions([$command->extensionKey => new ArrayStruct($command->extensionsPayload)]);
    }
}
